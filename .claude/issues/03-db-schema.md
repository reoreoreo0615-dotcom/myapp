筋トレ管理アプリ **Overload** のスキーマを確定し、マイグレーションを作成する。

> 設計ドキュメント: https://claude.ai/code/artifact/960e32f1-ad73-400d-9f21-eddde8965ca0

このIssueが全ての前提になる。スキーマが固まる前に他の実装に着手しないこと。

## 作成するテーブル(6つ)

```
users
  └─ workouts          1回のトレーニングセッション
       └─ workout_sets    1セット(実測値)
              └─ exercises   種目マスタ
users
  └─ routines          メニューのテンプレート
       └─ routine_exercises
```

### `exercises` — 種目マスタ

| カラム | 型 | 備考 |
| --- | --- | --- |
| id | bigint unsigned PK | |
| user_id | bigint unsigned **nullable** | null = 既定種目、値あり = ユーザー独自種目 |
| name | string(80) | 例: ベンチプレス |
| muscle_group | enum | chest / back / shoulders / legs / arms / core |
| movement_type | enum | push / pull / legs / core(**Phase2用。今は未使用だが列は作る**) |
| equipment | enum | barbell / dumbbell / machine / cable / bodyweight |
| is_bodyweight | boolean default false | true のとき weight は「加重分」を意味する |
| weight_increment | decimal(4,2) default 2.50 | 漸進の刻み幅 |
| target_rep_min | tinyint unsigned default 8 | 目標レップ下限 |
| target_rep_max | tinyint unsigned default 12 | 目標レップ上限 |
| sort_order | smallint unsigned default 0 | |
| timestamps | | |

- FK: `user_id` → `users.id` / `nullOnDelete`
- index: `(user_id, sort_order)`

### `workouts` — セッション

| カラム | 型 | 備考 |
| --- | --- | --- |
| id | bigint unsigned PK | |
| user_id | bigint unsigned | |
| routine_id | bigint unsigned **nullable** | メニューなしの飛び込みトレも記録できるように nullable |
| performed_on | date | 実施日。集計の軸 |
| started_at / finished_at | datetime nullable | トレーニング時間の計測用 |
| memo | text nullable | 「寝不足」等。停滞の解釈に効く |
| timestamps | | |

- FK: `user_id` → `users.id` / `cascadeOnDelete`、`routine_id` → `routines.id` / `nullOnDelete`
- index: `(user_id, performed_on)`

### `workout_sets` — 実測値(アプリの重心)

| カラム | 型 | 備考 |
| --- | --- | --- |
| id | bigint unsigned PK | |
| workout_id | bigint unsigned | |
| exercise_id | bigint unsigned | |
| set_number | tinyint unsigned | セッション内のセット順 |
| weight | decimal(5,2) default 0 | 999.99kg まで。0.25kg 刻みも表現可 |
| reps | smallint unsigned | |
| rpe | decimal(3,1) nullable | 6.0〜10.0。**Phase2 のナビ補正用。今は未使用だが列は作る** |
| is_warmup | boolean default false | **必須。**アップのセットを集計・記録更新・ナビ計算から除外する |
| memo | string(255) nullable | |
| timestamps | | |

- FK: `workout_id` → `workouts.id` / `cascadeOnDelete`、`exercise_id` → `exercises.id` / `restrictOnDelete`
- index: `(exercise_id, workout_id)` … **「この種目の前回の記録」クエリ用。最重要**

### `routines` / `routine_exercises`

- `routines`: id, user_id, name(string 60), description(text nullable), sort_order, timestamps
- `routine_exercises`: id, routine_id, exercise_id, sort_order, target_sets(tinyint default 3), timestamps
  - unique: `(routine_id, exercise_id)`

## 設計上の決定事項

- **`movement_type` と `rpe` は MVP では使わないが、列は最初に作る。** データが積まれた後のカラム追加とバックフィルは、今なら数秒の作業を数時間に変えるため。
- **`is_warmup` を必ず持つ。** ウォームアップを混ぜて集計すると総ボリュームも自己ベストもナビの計算もすべて狂う。
- 全テーブルが `user_id` を持つマルチユーザー前提の設計にする(当面は自分専用でも、後から入れるのは高コスト)。

## 受入条件

- [ ] 6テーブル分のマイグレーションが作成されている
- [ ] `php artisan migrate:fresh` が通る
- [ ] Eloquent モデルとリレーション(hasMany / belongsTo)が定義されている
- [ ] 各モデルに `$fillable` と `casts`(date / decimal / boolean)が設定されている
- [ ] `php artisan migrate:rollback` でエラーなく戻せる(FK の削除順)
