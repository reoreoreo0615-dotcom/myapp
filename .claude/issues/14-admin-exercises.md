既定種目(`exercises` の `user_id = NULL` の31件)を管理画面から追加・編集・削除できるようにする。

> 設計仕様書: https://claude.ai/code/artifact/960e32f1-ad73-400d-9f21-eddde8965ca0

## なぜ必要か

現状、既定種目を変更するには `ExerciseSeeder.php` を書き換えて `make seed` を
再実行するしかない。**通っているジムのマシンの刻み幅に合わせて `weight_increment` を
直したい**、といった現実的な要求に対応できない。

`weight_increment` と `target_rep_min/max` は**漸進的過負荷ナビの入力そのもの**なので、
ここが実態と合っていないとナビの提示が的外れになる。

## 画面

`/admin/exercises`

- [ ] 既定種目の一覧(部位でグループ化、`sort_order` 順)
- [ ] 新規追加 / 編集 / 削除
- [ ] 並び替え(`sort_order` の一括更新)
- [ ] 部位・器具でのフィルタ

### 編集できる項目

`name` / `muscle_group` / `movement_type` / `equipment` / `is_bodyweight` /
`weight_increment` / `target_rep_min` / `target_rep_max` / `sort_order`

## バリデーション

- [ ] `target_rep_min <= target_rep_max`(逆転を許さない)
- [ ] `weight_increment > 0`(0 だとナビが永久に重量を上げられなくなる)
- [ ] `name` は `user_id = NULL` の範囲で重複不可
- [ ] enum 3種は定義された値のみ

## 削除時の注意

`workout_sets.exercise_id` は **RESTRICT** なので、**誰かが記録に使った種目は削除できない。**

- [ ] 削除しようとして FK 制約で失敗した場合、「この種目は N 件の記録で使われているため
      削除できません」と**具体的な件数を出して**説明する。500 エラーにしない
- [ ] 一覧画面に「記録件数」を表示し、削除できない種目が事前に分かるようにする

## 受入条件

- [ ] `/admin/exercises` で既定種目の一覧・追加・編集・削除ができる
- [ ] 並び替えが永続化される
- [ ] `target_rep_min > target_rep_max` を弾く
- [ ] `weight_increment = 0` を弾く
- [ ] 使用中の種目を削除しようとすると、件数付きのエラーメッセージが出る(500 にならない)
- [ ] 非管理者がアクセスすると 403
- [ ] 編集した `weight_increment` が ProgressionService の計算に反映される
      (種目を編集 → 次回の目標が新しい刻みで算出されることをテストで確認)
- [ ] Feature テストが通る
- [ ] Pint 整形済み

## 依存

- Issue #13(管理者権限の基盤)の完了後
