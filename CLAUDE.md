## 解説の際は、すべて日本語で説明すること

## 慎重に考え、私が与えた特定のタスクのみに取り組み、できる限りコードの変更を少なくして、最も簡潔でエレガントな解決策を実行してください。

## AIアシスタントへの注意事項

When working on this codebase:

1. **Always run php -l [PHPファイル名] and fix warnings** before suggesting code
2. **Test your changes** - don't assume code works
3. **Preserve existing behavior** unless explicitly asked to change it
4. **Follow PSR-1, PSR-2 and PSR-12*** - basically follow other PSRs, too. But ignore the PSRs abandoned.
5. **Maintain predictable defaults** - user should never be surprised
6. **Document any new features** in both code and README
7. **Consider edge cases** - empty states, missing files, permissions

Remember: This tool is about speed and simplicity.
Every feature should make context switching faster or easier, not more complex.
**Predictability beats cleverness.**


## タスク実行の4段階フロー

### 1. 要件定義
.claude_workflow/complete.mdが存在すれば参照
目的の明確化、現状把握、成功基準の設定
.claude_workflow/requirements.mdに文書化
**必須確認**: 「要件定義フェーズが完了しました。設計フェーズに進んでよろしいですか？」

### 2. 設計
**必ず.claude_workflow/requirements.mdを読み込んでから開始**
アプローチ検討、実施手順決定、問題点の特定
.claude_workflow/design.mdに文書化
**必須確認**: 「設計フェーズが完了しました。タスク化フェーズに進んでよろしいですか？」

### 3. タスク化
**必ず.claude_workflow/design.mdを読み込んでから開始**
タスクを実行可能な単位に分解、優先順位設定
.claude_workflow/tasks.mdに文書化
**必須確認**: 「タスク化フェーズが完了しました。実行フェーズに進んでよろしいですか？」

### 4. 実行
**必ず.claude_workflow/tasks.mdを読み込んでから開始**
タスクを順次実行、進捗を.claude_workflow/tasks.mdに更新
各タスク完了時に報告

## 実行ルール
### ファイル操作
新規タスク開始時: 既存ファイルの**内容を全て削除して白紙から書き直す**
ファイル編集前に必ず現在の内容を確認

### フェーズ管理
各段階開始時: 「前段階のmdファイルを読み込みました」と報告
各段階の最後に、期待通りの結果になっているか確認
要件定義なしにいきなり実装を始めない

### 実行方針
段階的に進める: 一度に全てを変更せず、小さな変更を積み重ねる
複数のタスクを同時並行で進めない
エラーは解決してから次へ進む
エラーを無視して次のステップに進まない
指示にない機能を勝手に追加しない
不明な部分は、随時質問して解決する
新しくＷｅｂアプリを追加する場合は、requirements.md,design.md,tasks.mdに追記する形で要件定義等を行うこと。
実装が管理用したら、実装内容や機能について、README.mdに追記すること。