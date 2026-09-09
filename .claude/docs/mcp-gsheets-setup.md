# Google Sheets MCP のセットアップ手順

学習記録などをスプレッドシートに直接読み書きできるようにする。

- 使用サーバー: [freema/mcp-gsheets](https://github.com/freema/mcp-gsheets)
- 認証方式: **サービスアカウント**(OAuth のブラウザ認証が不要で、非対話セッションでも動く)
- 要件: Node.js v20 以上(このマシンは v22.15.0 で条件を満たす)

---

## 1. Google Cloud 側の設定(所要 10分程度)

### ① プロジェクトを作成
https://console.cloud.google.com/ で新規プロジェクトを作成する。
既存のものがあればそれでよい。**プロジェクトIDを控えておく**(後で使う)。

### ② Google Sheets API を有効化
`APIs & Services` → `Library` → **Google Sheets API** を検索 → `Enable`

### ③ サービスアカウントを作成
`APIs & Services` → `Credentials` → `Create Credentials` → `Service Account`

名前は何でもよい(例: `claude-code-sheets`)。ロールの付与は不要
(スプレッドシート側で個別に共有するため)。

### ④ JSON キーを発行
サービスアカウント一覧 → 対象の行の `⋮`(Actions)→ `Manage keys`
→ `Add key` → `Create new key` → **JSON** を選択

JSON ファイルがダウンロードされる。

### ⑤ キーを配置する

```bash
mv ~/Downloads/<ダウンロードしたファイル>.json \
   /Applications/MAMP/htdocs/laravel/myapp/.claude/secrets/gcp-service-account.json
```

> **このファイルは `.gitignore` 済みでコミットされない。**
> 漏れると共有したスプレッドシートを第三者が読み書きできるので、
> 絶対にリポジトリや Slack 等に貼らないこと。

---

## 2. スプレッドシート側の共有設定(所要 1分)

**これを忘れると「アクセスできない」エラーになる。**

1. JSON キーの中の `client_email` の値をコピーする
   (`xxxx@yyyy.iam.gserviceaccount.com` の形式)
2. 対象のスプレッドシートを開く
3. 右上の「共有」→ そのメールアドレスを追加 → 権限を **編集者** にする

---

## 3. リポジトリ側の設定

`.mcp.json` は作成済み。**プロジェクトIDだけ書き換える。**

```jsonc
{
  "mcpServers": {
    "gsheets": {
      "command": "npx",
      "args": ["-y", "mcp-gsheets@latest"],
      "env": {
        "GOOGLE_PROJECT_ID": "PUT_YOUR_PROJECT_ID_HERE",   // ← ここを実際のIDに
        "GOOGLE_APPLICATION_CREDENTIALS": "/Applications/MAMP/htdocs/laravel/myapp/.claude/secrets/gcp-service-account.json"
      }
    }
  }
}
```

CLI で追加する場合はこちらでもよい:

```bash
claude mcp add gsheets npx -y mcp-gsheets@latest
```

---

## 4. 反映と確認

**MCP サーバーはセッション開始時に読み込まれる。** 設定後は Claude Code を再起動すること。

再起動後、以下を依頼すれば疎通確認できる:

> 「gsheets の `sheets_check_access` で、このスプレッドシートにアクセスできるか確認して」
> (スプレッドシートの URL を一緒に渡す)

---

## 使えるようになる操作(主なもの)

| 分類 | できること |
| --- | --- |
| 読み取り | セルの値取得、メタデータ取得、アクセス確認 |
| 書き込み | セル更新、一括更新、追記、クリア、行の挿入・削除 |
| シート管理 | シートの作成・削除・複製、スプレッドシート新規作成 |
| 書式 | セルの書式設定、罫線、セル結合、条件付き書式 |
| その他 | テーブル、グラフの作成・更新 |

計40種類以上のツールが使える。

---

## トラブルシューティング

| 症状 | 原因と対処 |
| --- | --- |
| アクセスできない | **スプレッドシートをサービスアカウントのメールアドレスに共有していない**(手順2)。最も多い原因 |
| 認証エラー | `GOOGLE_APPLICATION_CREDENTIALS` のパスが間違っている。**絶対パス**で書くこと |
| サーバーが起動しない | Node.js のバージョンを確認(v20以上が必要) |
| 設定しても認識されない | Claude Code を再起動していない |

---

## セキュリティ上の注意

- **サービスアカウントのキーはパスワードと同等。** 流出したら Google Cloud Console から
  該当キーを削除して再発行すること
- サービスアカウントには**必要なスプレッドシートだけを個別に共有する。**
  Google Drive 全体への権限は与えない
- `.claude/secrets/` は `.gitignore` 済みだが、**コミット前に `git status` で
  確認する習慣をつけること**
