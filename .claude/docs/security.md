# セキュリティ対策の調査と検証(Issue #29)

**目的**: 「Laravel/Vue が自動で守ってくれている」ことは知っていても、何をどう守っているかを
自分の言葉で説明できない状態を解消する。すべての記述は実際のソースコード(`vendor/` 内含む)・
設定ファイル・HTTP レスポンスで裏を取ったもののみを記載する。「〜だと思われる」は書かない。

- 検証日: 2026-09-09
- 検証環境: `docker compose` ローカル環境(`APP_ENV=local`, `APP_URL=http://localhost`)
- 検証ユーザー: `admin@example.com`(id=1, is_admin=true)/ `test@example.com`(id=2, is_admin=false)
- Laravel: 13.30.1 / PHP: 8.3.33 / Inertia.js + Vue 3

---

## 1. CSRF(Cross-Site Request Forgery)

### 1.1 検証しているミドルウェア

`web` ミドルウェアグループに以下が組み込まれている(アプリ側で明示的に追加してはいない。
Laravel のデフォルト)。

`app/vendor/laravel/framework/src/Illuminate/Foundation/Configuration/Middleware.php:484-495`

```php
'web' => array_values(array_filter([
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
    $this->authenticatedSessions ? 'auth.session' : null,
])),
```

実際に検証を行うクラスは
`Illuminate\Foundation\Http\Middleware\PreventRequestForgery`
(`app/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestForgery.php`)。

古い `VerifyCsrfToken` は Laravel 13 では次のように `PreventRequestForgery` を継承するだけの
`@deprecated` クラスになっている(`.../Middleware/VerifyCsrfToken.php:1-11`)。

```php
/**
 * @deprecated Use PreventRequestForgery instead.
 */
class VerifyCsrfToken extends PreventRequestForgery {}
```

### 1.2 判定ロジック(重要: トークン照合だけではない)

`PreventRequestForgery::handle()`(115行目)は次の**いずれか**が真なら通す。1つも満たさなければ
`TokenMismatchException` → HTTP 419。

```php
if (
    $this->isReading($request) ||        // GET/HEAD/OPTIONS
    $this->runningUnitTests() ||
    $this->inExceptArray($request) ||    // except() で明示除外したパス
    $this->hasValidOrigin($request) ||   // ← Laravel 12以降で追加。Sec-Fetch-Site ヘッダ
    $this->tokensMatch($request)         // 従来のトークン照合
) { ... }
throw new TokenMismatchException('CSRF token mismatch.');
```

`hasValidOrigin()`(143-160行目)はブラウザが自動付与する `Sec-Fetch-Site` ヘッダ(JS からは
偽装不可能な Fetch Metadata)を見て、`same-origin` ならその時点でトークンチェックを経ずに許可する。
これは「同一オリジンからのリクエストであることをブラウザ自身が保証している」ことを利用した、
トークン方式より新しい防御層。

**実際に検証した**(`curl` は `Sec-Fetch-Site` を送らない):

```
$ curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost/login \
    -H "Sec-Fetch-Site: same-origin" -d 'email=a@b.c&password=x'
302   ← CSRFチェックは通過し、認証失敗(バリデーションエラー)としてリダイレクト

$ curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost/login \
    -H "Sec-Fetch-Site: cross-site" -d 'email=a@b.c&password=x'
419   ← トークンも無いので拒否
```

→ このアプリは Fetch Metadata と トークン照合の**二段構え**で守られている。ブラウザ経由の
リクエストは基本的に `Sec-Fetch-Site` の時点で守られ、curl 等ヘッダを送らないツールやこの
ヘッダに対応しない古いブラウザに対してはトークン照合がフォールバックになる。

### 1.3 トークン無し POST が 419 になることの確認

```
$ curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost/login \
    -d 'email=a@b.c&password=x'
419
```

レスポンスヘッダ・ボディ(抜粋、`curl -i`):

```
HTTP/1.1 419 unknown status
...
Set-Cookie: overload-session=...; httponly; samesite=lax
<title>Page Expired</title>
```

### 1.4 トークンがどこで生成され、どうフロントに渡っているか

- サーバ側: `$request->session()->token()` がセッションに紐づく CSRF トークン(`_token`)を保持。
  `PreventRequestForgery::addCookieToResponse()`(219-230行目)がレスポンスのたびに
  `XSRF-TOKEN` クッキー(暗号化された値)としてブラウザに書き戻す(`newCookie()` 239-253行目)。
- フロント側: このアプリの `resources/js/bootstrap.js` は axios の `X-Requested-With` ヘッダ
  以外は何も設定していない。**独自の CSRF 実装は無い。**
  `resources/js/app.js` の Inertia は内部で axios を使っており、axios のデフォルト設定
  (`node_modules/axios/lib/defaults/index.js:151-152`)

  ```js
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  ```

  により、同一オリジンへのリクエストでは `XSRF-TOKEN` クッキーの値を自動的に読み取り
  `X-XSRF-TOKEN` ヘッダとして送信する。`PreventRequestForgery::getTokenFromRequest()`
  (183-196行目)がこのヘッダを受け取り、暗号化を解いて `_token` 相当として照合する。
  → **Blade の `@csrf` は使っていない/使う必要が無い**(SPA なので `<form>` の隠し `input` に
  頼らず、axios がクッキー経由で自動送信する)。

### 1.5 除外設定(except)の有無

`app/bootstrap/app.php` を確認したが、`->withMiddleware()` 内に
`$middleware->validateCsrfTokens(except: [...])` の呼び出しは無い。**CSRF 除外パスは0件。**
すべての state を変更するリクエスト(GET/HEAD/OPTIONS 以外)が対象。

### 1.6 SPA(Inertia)特有の注意点

- フォーム送信も Inertia の `router.post()` 等を使う限り axios 経由になるため、①の Fetch
  Metadata と②の `XSRF-TOKEN` クッキーの自動送信が両方効く。**Blade テンプレート側で
  トークンを埋め込む必要は一切ない。**
- Inertia のページ本体(`data-page` 属性)には CSRF トークンは含まれていない
  (`vendor/inertiajs/inertia-laravel/src/Directive.php:24-30`、後述 XSS 節参照)。トークンは
  常にクッキー経由。

---

## 2. XSS(Cross-Site Scripting)

### 2.1 Blade の `{{ }}` と `{!! !!}` の違い

- `{{ $x }}` は `htmlspecialchars($x, ENT_QUOTES, 'UTF-8')` 相当のエスケープを行う
  (Blade コンパイラの `e()` ヘルパー呼び出しに展開される)。
- `{!! $x !!}` はエスケープせずそのまま出力する。

**このアプリでの実際の使用箇所を grep した:**

```
$ grep -rn '{!!' resources/views/
(該当なし)
```

`resources/views/` には Blade テンプレートが `app.blade.php` の1枚しかなく、その中身は
`@routes` `@vite` `@inertiaHead` `@inertia` という Laravel/Inertia 標準ディレクティブのみで、
`{!! !!}` の直書きは一切無い。

### 2.2 Vue の `{{ }}` と `v-html` の違い、実際の使用箇所

- Vue の `{{ }}`(テキスト補間)はコンパイル時に `textContent` への代入相当のコードになり、
  HTML として解釈されない(常に安全)。
- `v-html` は与えた文字列を `innerHTML` にそのまま突っ込むため、XSS の入口になり得る。

**実際に grep した結果:**

```
$ grep -rn "v-html" resources/js/
(該当なし。exit code 1 = マッチ無し)
```

**このアプリには `v-html` の使用箇所が1つも無い。** 種目名などユーザー入力に由来する文字列は
すべて `{{ exercise.name }}` のような通常のテキスト補間で描画されている
(例: `resources/js/Pages/Workouts/Show.vue:515`, `:623`)。

### 2.3 実際に検証した: 種目名に `<script>alert(1)</script>` を登録

手順:
1. ログイン済みセッションで `POST /exercises`(`name=<script>alert(1)</script>`)を送信し、
   実際に DB へ保存されることを確認(id=32, `user_id=1` で作成)。
2. この種目をルーティンに紐付け、`GET /workouts/{id}` の**素の HTML レスポンス**
   (Inertia の初回ロード、SPA 化前の生 HTML)を取得し、どう出力されるかを確認。
3. 検証後、作成した exercise / routine / workout を全て削除。

**実際のレスポンス(抜粋、`curl` で取得した生 HTML)**:

```
,&quot;exercises&quot;:[{&quot;id&quot;:32,&quot;name&quot;:&quot;&lt;script&gt;alert(1)&lt;\/script&gt;&quot;, ...
```

`grep -c "<script>alert" show20.html` → **0**(実行可能なタグとしては一切出現しない)。

なぜこうなるか、コード上の根拠:

`app/vendor/inertiajs/inertia-laravel/src/Directive.php:24-30`(`@inertia` ディレクティブの実体)

```php
} elseif (config('inertia.use_script_element_for_initial_page')) {
    ?><script data-page="'.$id.'" ...>{!! json_encode($page) !!}</script>...
} else {
    ?><div id="'.$id.'" data-page="{{ json_encode($page) }}"></div><?php
}
```

このアプリは `config('inertia.use_script_element_for_initial_page')` を有効化していない
(`.env` に `INERTIA_USE_SCRIPT_ELEMENT_FOR_INITIAL_PAGE` の設定無し。デフォルト `false`。
`vendor/inertiajs/inertia-laravel/config/inertia.php:67`)ため、**後者の分岐**
`data-page="{{ json_encode($page) }}"` が使われる。ここは Blade の `{{ }}` なので、
`json_encode()` が生成した JSON 文字列全体が HTML エスケープされ、`<` は `&lt;`、`"` は
`&quot;` になる。ブラウザは属性値を読むときにこの実体参照をデコードして元の JSON 文字列
(`<script>alert(1)</script>` を含む)に戻すが、それは**属性値としての文字列データ**でしか
なく、その後 Vue が `JSON.parse()` してオブジェクト化し、`{{ exercise.name }}` で
テキストとして描画するだけなので、どの段階でも `<script>` タグとして解釈されない。

→ **HTML 属性エスケープ(Blade)+ Vue のテキスト補間**という二重の防御になっている。

### 2.4 CSP(Content-Security-Policy)ヘッダ

**送っていない。** 確認方法:

```
$ curl -s -I http://localhost/
HTTP/1.1 200 OK
Server: nginx/1.27.5
Content-Type: text/html; charset=utf-8
...
(Content-Security-Policy ヘッダ無し)
```

`app/` 内に CSP を設定するミドルウェア・パッケージ(`spatie/laravel-csp` 等)は無い
(`composer.json` に該当パッケージ無し)。

**判断: 今回は導入を見送り、事実だけ記録する。** 理由は以下(詳しくは §5 参照):
- 上記の通り、この構成(Blade 属性エスケープ + Vue 標準補間、`v-html` 不使用)であれば
  反射型/格納型 XSS が実行される具体的な経路が現状無い。CSP は「多層防御」としての価値はあるが、
  Google Fonts の `https://fonts.googleapis.com` / `https://fonts.gstatic.com` を読み込んでいる
  (`resources/views/app.blade.php:10-12`)ため、`default-src 'self'` 一発では壊れる。
  ポリシー設計自体がこの Issue の対象範囲外の作業になるため、**未対応であることを明記するに
  留める**(§5)。

---

## 3. セッション管理

### 3.1 セッションドライバ・保存先

`.env`: `SESSION_DRIVER=database`。`config/session.php:21` の既定もこれと同じ `database`。

実データは `sessions` テーブル(`database/migrations/0001_01_01_000000_create_users_table.php:30-37`)。

```php
Schema::create('sessions', function (Blueprint $table) {
    $table->string('id')->primary();
    $table->foreignId('user_id')->nullable()->index();
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->longText('payload');
    $table->integer('last_activity')->index();
});
```

`payload` 列にセッションデータがシリアライズされて入る(暗号化は `SESSION_ENCRYPT=false` のため
未実施。ローカル DB 内なので閲覧できる想定)。

### 3.2 `SESSION_LIFETIME=120` の意味

`config/session.php:35` `'lifetime' => (int) env('SESSION_LIFETIME', 120)` は**分**単位。
実際に発行されたクッキーで確認:

```
Set-Cookie: overload-session=...; expires=...; Max-Age=7200; path=/; httponly; samesite=lax
```

`7200秒 = 120分` と一致。

切れたときの挙動: `lottery` 設定(`config/session.php:117` `'lottery' => [2, 100]`、
= リクエストごとに2%の確率でガベージコレクションが走る)により、`last_activity` が
`lifetime` 分より古い `sessions` 行が削除される。行が消えると次回そのクッキーでアクセスしても
未ログイン扱いになり、`auth` ミドルウェア(Laravel 標準の `Illuminate\Auth\Middleware\Authenticate`。
このアプリで上書きしていない)によりログイン画面へリダイレクトされる。ブラウザ側のクッキー自体も
`Max-Age` 経過で自動削除される。

### 3.3 ログイン時にセッションIDが再生成されるか(セッション固定攻撃対策)

**実際に検証した。** ログイン前後で `sessions` テーブルの行を比較。

```
ログイン前(このcurlセッションのCookie Jarが持つセッションID):
oYWveKvxeOcdO7QytfAt0Nl38YW7wn7ig4WoEGFB | user_id=(空)

ログイン成功後:
qHWQA4EFZReofYzcTeuyu2za7pMWyhTKxxj6Nddg | user_id=1  ← 新しいIDで作成
oYWveKvxeOcdO7QytfAt0Nl38YW7wn7ig4WoEGFB は行ごと消滅(destroy済み)
```

コード上の根拠は二段ある:

1. **`Auth::attempt()` 自身が内部で regenerate している**
   `app/vendor/laravel/framework/src/Illuminate/Auth/SessionGuard.php:567-598`

   ```php
   public function login(AuthenticatableContract $user, $remember = false)
   {
       $this->updateSession($user->getAuthIdentifier());
       ...
   }

   protected function updateSession($id)
   {
       $this->session->put($this->getName(), $id);
       $this->session->regenerate(true);   // ← true = 古いセッションをdestroyしつつ再生成
   }
   ```

2. **アプリのコントローラも明示的に呼んでいる(冗長だが無害)**
   `app/app/Http/Controllers/Auth/AuthenticatedSessionController.php:30-34`

   ```php
   public function store(LoginRequest $request): RedirectResponse
   {
       $request->authenticate();          // 内部で Auth::attempt() → 上記1が既に発火
       $request->session()->regenerate(); // さらにもう一度明示的に呼んでいる
       ...
   }
   ```

   `Illuminate\Session\Store::regenerate()`(`.../Session/Store.php:618-636`)は
   `migrate()`(新しいセッションIDを発行、`$destroy=true` なら旧行を削除)と
   `regenerateToken()`(CSRF トークンも同時に再発行)を両方行う。

→ **セッションID・CSRFトークンともにログインのたびに再発行される**ため、ログイン前に
第三者が仕込んだセッションIDを使わせるセッション固定攻撃は成立しない。

ログアウト時も同様に確認できる
(`AuthenticatedSessionController::destroy()` 40-49行目):

```php
Auth::guard('web')->logout();
$request->session()->invalidate();     // 全セッションデータ破棄+ID再生成
$request->session()->regenerateToken();
```

### 3.4 `HttpOnly` / `SameSite` / `Secure` の設定値

`config/session.php`:

| 設定 | 行 | 値 | 実際のレスポンスヘッダでの確認 |
|---|---|---|---|
| `http_only` | 185行目 | `env('SESSION_HTTP_ONLY', true)` → `true` | `Set-Cookie: overload-session=...; httponly` ✓ |
| `same_site` | 202行目 | `env('SESSION_SAME_SITE', 'lax')` → `lax` | `; samesite=lax` ✓ |
| `secure` | 172行目 | `env('SESSION_SECURE_COOKIE')` → `.env` に未設定なので `null` | `Secure` 属性は付いていない(ローカルが `http://` のため) |

`Secure` 属性が付いていない点は、**本番運用時に `SESSION_SECURE_COOKIE=true`
(または HTTPS 配下で `APP_URL` を `https://` にする)を明示的に設定しないと、HTTPS 環境でも
Cookie が誤って平文 HTTP 経路に乗り得る**という意味で要注意(§5 に記載)。ローカル開発
(`APP_ENV=local`, `http://localhost`)としては妥当な状態。

### 3.5 `remember me` の保存・検証

チェックボックス: `resources/js/Pages/Auth/Login.vue:74`(`v-model:checked="form.remember"`)。

- `users` テーブルに `remember_token` 列がある
  (`database/migrations/0001_01_01_000000_create_users_table.php:20` `$table->rememberToken();`)。
- ログイン成功時、`remember=true` なら `SessionGuard::queueRecallerCookie()`
  (`.../SessionGuard.php:620-627`)が
  `"{ユーザーID}|{remember_token}|{パスワードハッシュのHMAC}"` を暗号化した
  クッキー(`remember_web_...`)を発行する(平文の `remember_token` そのものは
  クッキーに乗らず、HMAC 経由)。
- 次回訪問時、セッションが切れていてもこのクッキーがあれば
  `SessionGuard::userFromRecaller()`(`.../SessionGuard.php:215-238`)が
  `retrieveByToken()`(`EloquentUserProvider.php:68-83`、内部は
  `hash_equals($rememberToken, $token)` によるタイミング攻撃耐性のある比較)で
  DB の `remember_token` と突き合わせ、一致すれば自動ログインする。
- このクッキー自体も `EncryptCookies` ミドルウェアの対象(除外リストにアプリ側で
  何も追加していないため、デフォルトで全クッキーが暗号化される)。

---

## 4. SQLインジェクション

### 4.1 Eloquent / クエリビルダの防御機構

`Illuminate\Database\Connection` はクエリを常に PDO のプリペアドステートメント経由で実行する。

```
$ grep -n "prepare(" vendor/laravel/framework/src/Illuminate/Database/Connection.php
435:  $this->getPdoForSelect($useReadPdo)->prepare($query)
463:  ...
500:  ...
595:  $statement = $this->getPdo()->prepare($query);
622:  ...
```

`where('col', $value)` のような通常の呼び出しは、SQL 文字列には `?` プレースホルダが入り、
`$value` は別途バインディング配列として PDO に渡される。文字列連結で SQL を組み立てないため、
`$value` にどんな文字列(`' OR '1'='1` 等)が来ても SQL 構文としては解釈されない。

### 4.2 このアプリでの生SQL使用箇所を grep した

```
$ grep -rn "DB::raw(\|DB::statement(\|DB::unprepared(" app/
(該当なし)
```

**`DB::raw()` は一切使われていない。**

```
$ grep -rl "selectRaw\|whereRaw\|orderByRaw" app/
app/Repositories/WorkoutSetRepository.php
```

`selectRaw` を使っているのはこの1ファイルのみ(集計・window関数のため)。中身を全行確認した結果:

- ユーザー入力(日付フィルタ `$onOrBeforeDate` / `$monthStart` / `$thisWeekStart` など)を
  埋め込む箇所は、**すべて `selectRaw($sql, $bindings)` の第2引数(バインディング配列)経由**で
  渡している。例(`WorkoutSetRepository.php:473-480`):

  ```php
  ->selectRaw(
      'SUM(CASE WHEN workouts.performed_on >= ? THEN workout_sets.weight * workout_sets.reps ELSE 0 END) as this_week',
      [$thisWeekStart],
  )
  ```

  → `?` プレースホルダなので文字列連結ではなくプリペアドステートメント。

- PHP の文字列補間 `{$metricExpr}` で SQL 文に埋め込んでいる箇所(`personalBest()` 328-334行目、
  `monthlyBestComparison()` 530-545行目、`latestPersonalBest()` 614-632行目)があるが、
  `$metricExpr` は**すべてコード内に固定でハードコードされた CASE 式の文字列**であり、
  リクエストや DB から来た値ではない(ユーザー入力が混入する余地が無い)。

- `whereIn('workout_id', $workoutIds)` のように配列を渡す箇所は Eloquent 標準の
  `whereIn`(バインディング展開)を使っており、生SQL構築ではない。

**結論: 危険な生SQL(ユーザー入力の直接文字列連結)は見つからなかった。**

### 4.3 実際に検証した

CSV エクスポートの日付フィルタ(`from`/`to`)に SQLi ペイロードを送信:

```
$ curl -s "http://localhost/export/workouts?from=2024-01-01%27%20OR%20%271%27%3D%271" \
    -b cookies.txt -c cookies.txt -H "Accept: application/json" -w "\nSTATUS:%{http_code}\n"
STATUS:422
{"message":"from には有効な日付を指定してください。","errors":{"from":["from には有効な日付を指定してください。"]}}
```

`app/app/Http/Requests/ExportPeriodRequest.php:26-27` の `'from' => ['nullable', 'date']`
バリデーションで、日付として解釈できない文字列はそもそもコントローラに到達する前に 422 で
弾かれる。仮にバリデーションが無くても、`where('workouts.performed_on', '>=', $fromDate)`
はバインディング経由なので破壊的な結果にはならない(§4.1)。

---

## 5. 認可(このアプリ固有)

### 5.1 Policy が守っているもの

`app/app/Policies/` に3つの Policy が存在(すべて命名規則によりフレームワークが自動解決。
`AuthServiceProvider` 等での明示登録は無い)。

| Policy | 対象モデル | 判定方法(全メソッド共通) |
|---|---|---|
| `WorkoutPolicy` | `Workout` | `$user->id === $workout->user_id`(+一部 `finished_at`/`editing_started_at` の状態条件) |
| `RoutinePolicy` | `Routine` | `$user->id === $routine->user_id` |
| `BodyLogPolicy` | `BodyLog` | `$user->id === $bodyLog->user_id` |

コントローラ側は `$this->authorize('view'|'update'|'delete'|..., $model)` を明示的に呼んでいる。
`workout_sets` / `routine_exercises` のように親を通じて認可する子リソースは、親モデルの
Policy(`update` 等)を経由する設計(例: `WorkoutSetController.php:98,115` で
`$this->authorize('update', $workout)`)。

### 5.2 実際に検証した: 他ユーザーのワークアウトへのアクセス

手順: `user_id=2`(自分ではない別ユーザー)所有の一時的な workout(id=19)を作成し、
`user_id=1` としてログインしたセッションでアクセス。検証後、直ちに削除。

```
$ curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt http://localhost/workouts/1   # 自分の記録
200

$ curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt http://localhost/workouts/19  # 他人の記録
403

$ curl -s -b cookies.txt http://localhost/workouts/19 -H "Accept: application/json"
{
    "message": "This action is unauthorized.",
    "exception": "Symfony\\Component\\HttpKernel\\Exception\\AccessDeniedHttpException",
    ...
}

$ curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt http://localhost/workouts/99999  # 存在しないID
404
```

`WorkoutController::show()`(`app/app/Http/Controllers/WorkoutController.php:97`)の
`$this->authorize('view', $workout)` が `WorkoutPolicy::view()` を呼び、所有者不一致で
`AccessDeniedHttpException`(→ 403)を投げていることを確認した。

### 5.3 管理者専用ルートの検証(このアプリ固有の追加ゲート)

`admin` ミドルウェアエイリアス(`app/bootstrap/app.php`)→
`EnsureUserIsAdmin::handle()`(`app/app/Http/Middleware/EnsureUserIsAdmin.php:19-22`):

```php
if (! $request->user() || ! $request->user()->is_admin) {
    abort(403);
}
```

**実際に検証した**(`test@example.com` / `is_admin=false` としてログイン):

```
$ curl -s -o /dev/null -w "%{http_code}\n" -b cookies_user2.txt http://localhost/admin/users
403
```

### 5.4 付随して確認した: `auth.user` プロパティのパスワード非露出

Inertia で全ページに共有される `auth.user`(`app/app/Http/Middleware/HandleInertiaRequests.php:35-37`
`'auth' => ['user' => $request->user()]`)には `User` モデルがそのまま渡るが、
`app/app/Models/User.php:15` の PHP 属性

```php
#[Hidden(['password', 'remember_token'])]
```

により `password` / `remember_token` は JSON 化(配列化)の際に自動的に除外される。

**実際に検証した**(ログイン中の `dashboard` ページの生 HTML 内 `data-page` 属性を確認):

```
&quot;auth&quot;:{&quot;user&quot;:{&quot;id&quot;:1,&quot;name&quot;:&quot;テストユーザー&quot;,
&quot;email&quot;:&quot;admin@example.com&quot;,&quot;email_verified_at&quot;:null,
&quot;is_admin&quot;:true,&quot;created_at&quot;:...,&quot;updated_at&quot;:...}
```

`password` / `remember_token` フィールドは出力に含まれないことを確認した
(`grep -o "&quot;password&quot;..." dash.html` → マッチ無し)。

---

## 6. 対策できていない項目(正直に列挙する)

調査の過程で「やっていない」と分かったもの。**隠さず全部書く。**

1. **CSP(Content-Security-Policy)ヘッダを送っていない。**
   `curl -I http://localhost/` で確認済み(§2.4)。現状 `v-html` 不使用・Blade属性エスケープ
   により具体的な突破経路は見当たらないが、CSP は「将来のミス」に対する多層防御であり、未導入。
   Google Fonts を外部から読み込んでいるため `default-src 'self'` では壊れる。ポリシー設計は
   本 Issue の範囲外として対応していない。

2. **一般的なセキュリティヘッダが無い。** `curl -I` で確認した実際のレスポンスヘッダには
   `X-Content-Type-Options`(MIMEスニッフィング対策)、`X-Frame-Options` /
   `frame-ancestors`(クリックジャッキング対策)、`Referrer-Policy`、
   `Strict-Transport-Security`(HSTS。そもそもローカルはHTTPSでないので該当外だが本番でも未設定)
   のいずれも付与されていない。

3. **`Server: nginx/1.27.5` / `X-Powered-By: PHP/8.3.33` がそのまま露出している。**
   バージョン特定に使える情報漏えい(軽微だが本番では消すのが望ましい)。nginx 設定・
   `php.ini` の `expose_php` を変更していない。

4. **`SESSION_SECURE_COOKIE` が未設定(`null`)。** ローカルの `http://` では妥当だが、
   本番で HTTPS 配下に上げる際に明示的に `true` に設定し忘れると、Cookie が理論上 HTTP
   経路にも乗り得る状態になる(§3.4)。

5. **レート制限(ブルートフォース対策)が一貫していない。**
   - ログイン(`POST /login`)は `LoginRequest::ensureIsNotRateLimited()` により
     「メールアドレス+IPごとに5回失敗でロック」という**独自実装のレート制限**がある
     (`app/app/Http/Requests/Auth/LoginRequest.php:59-75`)。
   - パスワードリセットメール送信・メール確認再送は `throttle:6,1`(1分に6回)が付いている
     (`routes/auth.php:43,47`)。
   - **一方、新規登録(`POST /register`)には一切レート制限が無い**
     (`routes/auth.php:18`)。大量アカウント作成・メール送信の踏み台にされ得る。
   - ワークアウト記録・種目作成などアプリ本体の POST/PATCH/DELETE エンドポイントにも
     `throttle` ミドルウェアは付いていない(認証必須なので野放しの匿名連打ではないが、
     ログイン済みユーザーによる連続リクエストを制限する仕組みは無い)。

6. **DB 内のセッションデータは平文。** `SESSION_ENCRYPT=false`(`.env` の既定)のため、
   `sessions.payload` は暗号化されずに保存される。ローカル DB への直接アクセスを
   信頼する前提であれば許容範囲だが、明示的な選択ではなくデフォルト値のまま。

7. **CSRF の「same-origin なら無条件通過」という新方式(`hasValidOrigin()`)は
   `Sec-Fetch-Site` ヘッダを送らない/偽装できるクライアント(古いブラウザ、一部のネイティブ
   WebView、ヘッダを自由に設定できるツール)には効かず、その場合は完全にトークン方式に
   フォールバックする。** これは Laravel 側の設計であり脆弱性ではないが、「CSRFは
   Sec-Fetch-Site だけ見ていれば安全」という誤解をしないよう明記しておく。

---

## 7. まとめ(検証項目チェックリスト)

| # | 項目 | 検証方法 | 結果 |
|---|---|---|---|
| ① | CSRF: トークン無し POST → 419 | `curl` | ✓ 419 を確認 |
| ① | CSRF: `Sec-Fetch-Site` による同一オリジン免除 | `curl` + ヘッダ操作 | ✓ same-origin で通過、cross-site で419 |
| ① | CSRF: except 設定の有無 | `bootstrap/app.php` 読了 | ✓ 除外0件 |
| ② | XSS: `v-html` 使用箇所 | `grep -rn "v-html" resources/js/` | ✓ 0件 |
| ② | XSS: Blade `{!! !!}` 使用箇所 | `grep -rn '{!!' resources/views/` | ✓ 0件 |
| ② | XSS: `<script>alert(1)</script>` を種目名に登録して確認 | 実際に POST → HTML取得 → 削除 | ✓ `&lt;script&gt;` にエスケープされ実行不可を確認 |
| ② | XSS: CSPヘッダ | `curl -I` | ✗ 未送信(対策できていない) |
| ③ | セッション: ログイン時のID再生成 | `sessions` テーブルの前後比較 | ✓ 旧ID破棄・新ID発行を確認 |
| ③ | セッション: `HttpOnly`/`SameSite`/`Secure` | `curl -i` の `Set-Cookie` | ✓ httponly・samesite=lax確認、Secureは未設定(ローカルのため) |
| ④ | SQLi: `DB::raw()` 使用箇所 | `grep -rn "DB::raw(" app/` | ✓ 0件 |
| ④ | SQLi: `selectRaw` のバインディング | `WorkoutSetRepository.php` 全行読了 | ✓ ユーザー入力はすべて `?` バインディング経由 |
| ④ | SQLi: 日付フィルタへの注入試行 | `curl` | ✓ 422でバリデーション拒否を確認 |
| ⑤ | 認可: 他ユーザーのワークアウトへのアクセス | 実際に他ユーザーの workout を作成しアクセス→削除 | ✓ 403 を確認(自分のものは200、存在しないIDは404) |
| ⑤ | 認可: 管理者専用ルート | 非管理者ユーザーでログインしアクセス | ✓ 403 を確認 |

---

## 8. コード変更について

**このIssueではコードを一切変更していない。** 調査・検証・文書化のみ。
検証のために作成した以下のデータはすべて確認後に削除済み(開発DB `myapp` は現状復帰済み):

- `exercises` id=32(名前 `<script>alert(1)</script>`)→ 削除済み
- `routines` id=4(`XSS-test-routine`)→ 削除済み
- `workouts` id=19(user_id=2 所有、他ユーザーアクセステスト用)→ 削除済み
- `workouts` id=20(XSS検証用)→ 削除済み

削除後のレコード数を確認済み: `exercises` 31件(既定種目数と一致)、`users` 2件、
`workouts` 15件、`routines` 3件(すべて検証開始前の状態に一致)。

`migrate:fresh` 等の破壊的コマンドは実行していない(`myapp` に対しては一度も実行せず)。

## 9. テスト結果

```
$ docker compose exec php php artisan test
Tests:    373 passed (1687 assertions)
Duration: 49.76s

$ docker compose exec php npm run test:js
Test Files  11 passed (11)
Tests       70 passed (70)
```

コード変更が無いため当然ではあるが、既存テストが壊れていないことを実行して確認した。
