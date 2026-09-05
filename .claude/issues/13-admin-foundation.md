管理者権限の仕組みを追加し、ユーザー管理画面を作る。
あわせて**ユーザー削除時のデータ整合性バグを修正**する。

> 設計仕様書: https://claude.ai/code/artifact/960e32f1-ad73-400d-9f21-eddde8965ca0

## 先に直すべきバグ(最優先)

現在の外部キー定義:

```
exercises.user_id → users   DELETE_RULE: SET NULL
```

**ユーザーを削除すると、そのユーザーの独自種目が `user_id = NULL` になり、
全ユーザーに見える既定種目に昇格する。** private なデータが公開される情報漏洩。

これは管理者によるユーザー削除だけでなく、**Breeze が既に実装している
プロフィール画面の「アカウント削除」でも発生する**。

### 対処

1. `exercises.user_id` の削除ルールを **SET NULL → CASCADE** に変更する
   (ユーザーの独自種目はそのユーザーに属する、が正しいセマンティクス)
2. ただし `workout_sets.exercise_id` は **RESTRICT** なので、
   セット記録が残っている種目は削除できない。
   さらに MySQL は `users` から出る複数のカスケード経路の**実行順序を保証しない**ため、
   FK だけに頼るのは危険。
3. **アプリ側でトランザクションを張り、明示的な順序で削除する**
   `DeleteUserService`(または同等)を作る:

```
DB::transaction(function () use ($user) {
    $user->workouts()->delete();   // → workout_sets が CASCADE で消える
    $user->routines()->delete();   // → routine_exercises が CASCADE で消える
    $user->exercises()->delete();  // ← ここで初めて安全に消せる
    $user->delete();
});
```

4. **Breeze の `ProfileController@destroy` もこのサービスを使うように修正する。**
   自己削除でも同じバグが起きるため。

## 管理者権限の仕組み

### スキーマ

`users` に `is_admin`(boolean, default false, NOT NULL)を追加する。

**なぜ `role` enum ではなく boolean か:** 現時点で必要な区分は「管理者かどうか」の2値のみ。
enum にすると値の追加のたびにマイグレーションが必要になる割に、得られるものが無い。
3つ目のロールが必要になった時点で `role` へ移行する。

### ミドルウェア

`EnsureUserIsAdmin` を作成し、`admin` のエイリアスで登録する。
`is_admin = false` のユーザーがアクセスしたら **403** を返す(404 でも 302 でもなく)。

### ルート

```
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', ...)->name('users.index');
    Route::delete('/users/{user}', ...)->name('users.destroy');
});
```

## ユーザー管理画面

`/admin/users`

- [ ] 登録ユーザーの一覧(ID / 名前 / メール / 管理者フラグ / 登録日 / 記録件数)
- [ ] ユーザーの削除
- [ ] 管理者フラグの付与・剥奪

### 必須のガード

- [ ] **自分自身は削除できない**(誤操作で締め出されるため)
- [ ] **最後の管理者からは管理者フラグを剥奪できない**(同上)
- [ ] 削除は確認ダイアログを挟む
- [ ] 削除時に、そのユーザーのワークアウト・ルーティン・独自種目が
      すべて消えることを画面上で明示する

## 管理者アカウントの作成

既存ユーザー `admin@example.com`(id=7)に `is_admin = true` を付与する。
マイグレーション内でハードコードせず、**artisan コマンド**として作る:

```
php artisan user:make-admin {email}
```

理由: 環境ごとに管理者のメールアドレスは違うため、マイグレーションに埋め込むと
他環境で無意味なデータが入る。

## 受入条件

- [ ] `exercises.user_id` の削除ルールが CASCADE になっている
- [ ] `DeleteUserService` がトランザクション内で正しい順序で削除する
- [ ] `ProfileController@destroy` がこのサービスを使っている
- [ ] **ユーザーを削除しても、そのユーザーの独自種目が既定種目に昇格しないことを
      テストで検証している**(このバグの再発防止。最重要)
- [ ] `users.is_admin` が追加され、既定値が false
- [ ] `admin` ミドルウェアが非管理者に 403 を返す
- [ ] `/admin/users` で一覧・削除・管理者フラグ変更ができる
- [ ] 自分自身を削除できない / 最後の管理者から剥奪できない
- [ ] `php artisan user:make-admin {email}` が動作する
- [ ] `migrate:fresh` と `migrate:rollback` が両方通る
- [ ] 既存の58テストが全て通る
- [ ] Pint 整形済み
