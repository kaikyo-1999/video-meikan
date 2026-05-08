# 物理PC不依存のClaude Code デプロイ方式の比較

## 依頼

物理PC上のClaude Code CLIから本番サーバー（sv6810.wpx.ne.jp / Shinserver）へのデプロイを、PC依存なく実施できる方法を調査。「PC を閉じてもデプロイが継続」「外部サーバーへの SSH 接続」の観点で各方式の実現可能性を判定。

## 調査結果と比較

### 1. Claude Code on the web（claude.ai/code）

**現状・機能**
- 公式のブラウザベース実行環境。Anthropic 管理のクラウドインフラ上で動作
- GitHub リポジトリ連携可能、Session が永続化（ブラウザ閉鎖後も継続）
- SessionStart hook 対応で初期化時スクリプト実行可能

**SSH / 外部サーバーへのデプロイ可否**
- **制約あり**：クラウドサンドボックス環境のため、デフォルトではネットワーク接続制限あり
- SSH キーの埋め込みは可能（セキュリティ・権限管理が必須）
- 「Network access」設定で許可リスト管理が必要
- ドキュメント上では「configure cloud environments」の記載があるが、SSH 先への直接接続を前提とした実装例は明確でない

**料金・ライセンス**
- Pro / Max / Team / Enterprise（プレミアムシート）の有料プラン必須
- API 利用料金は別途

**推奨用途**
- GitHub リポジトリの自動テスト・ビルド・レビュー
- 長時間実行タスク（PC 依存しない）
- CI/CD トリガー、自動修正ワークフロー
- **デプロイ用途には向かない**（ネットワーク制限、SSH 接続の複雑性）

---

### 2. GitHub Actions / claude-code-action

**現状・機能**
- PR / Issue トリガーで GitHub ランナー上から Claude Code を実行
- GA v1.0 では `@claude` メンションで自動対応
- Azure Bedrock / Google Vertex AI による 3rd-party provider 対応

**SSH / 外部サーバーへのデプロイ可否**
- **可能**：GitHub Actions のランナーは通常の Linux 環境であり、SSH キーを Secret で設定可能
- `ssh-agent` + GitHub Secret に秘密鍵を保存して `deploy` コマンド実行が可能
- セキュリティ観点での推奨パターンが確立されている

**料金・ライセンス**
- GitHub Actions 実行分数での課金（無料枠あり：月 2000 分）
- パブリックリポジトリは無制限、プライベートはアカウント評価制
- API 料金別途

**推奨用途**
- **本番デプロイに最適**：SSH 接続・スクリプト実行・長時間実行が安定
- CI/CD パイプラインの最後の段階（test → build → deploy）
- セキュリティ要件が高い環境（監査ログ・権限管理が GitHub に統合）
- 但し、**「人が直接トリガーしない自動デプロイ」向け**（PR マージ後の自動反映等）
- **手動デプロイ指示に対応させたい場合は、issue / PR comment でトリガー仕様を設計する必要がある**

---

### 3. クラウド VM / 自前 Linux サーバー上で Claude Code CLI 常駐

**現状・機能**
- VPS / EC2 / 自前レンタルサーバー等で Claude Code をインストール、tmux / screen で常駐化
- ローカル CLI と同一機能が使用可能
- SSH キーは VM 内に直接配置可能

**ライセンス・認証**
- Claude Code CLI 実行には Anthropic API キーが必須
- CLI は 1 人のユーザーアカウントに紐付く（マルチユーザー環境では検証が必要）
- **料金体系は個人向けClaude Code と同一**

**推奨用途**
- **最も柔軟で推奨**（セキュリティ上も）
- SSH キーを VM ローカルに持ち、デプロイスクリプトから直接実行
- 継続的監視・ジョブスケジューリング（cron + Claude）が可能
- **本番デプロイのベストプラクティス**：鍵管理が VM 内に完結

**制約**
- VM のランニングコスト（月数千〜数万円）
- VM の管理・パッチ・監視が必要
- 鍵の更新・ローテーション時の対応

---

### 4. Claude Code Sandbox / Devcontainer

**現状・機能**
- 公式の開発用 sandbox（Docker ベース）。ローカルで隔離環境構築
- `devcontainer.json` で環境定義可能

**実用性**
- **デプロイには不適切**：sandbox は開発・テスト用で、本番接続を想定していない
- 物理 PC 依存は軽減されるが、実行環境は結局ローカルホストに依存

---

## 推奨される構成

### ケース A：「いますぐ SSH デプロイを堅牢に」

**GitHub Actions + claude-code-action（デプロイ特化型）**
- Issue / PR で `deploy` コマンド受け取り → claude-code-action が GitHub ランナーで実行
- ランナー内で `ssh-agent` に秘密鍵を読み込み、`bash deploy-script.sh` を実行
- 実行ログは GitHub Action 画面に記録、監査が容易

```yaml
# .github/workflows/deploy.yml 例
on:
  issue_comment:
    types: [created]
jobs:
  deploy:
    if: contains(github.event.comment.body, '@claude deploy')
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: anthropics/claude-code-action@v1
        with:
          prompt: "Deploy to production: ssh sv6810.wpx.ne.jp && cd ~/repo && php batch/run_all.php"
          anthropic_api_key: ${{ secrets.ANTHROPIC_API_KEY }}
```

**利点**：GitHub リポジトリが Source of Truth、PR 履歴でデプロイ追跡可能

---

### ケース B：「PC 依存なくいつでも Deploy、自由度高く」

**VPS（Vultr / Linode / AWS等）上で Claude Code CLI 常駐 + cron / Systemd**

```bash
# VPS上に SSH キーと Anthropic API キー配置
~/repo/
  .ssh/sv6810_key  # sv6810 への秘密鍵
  
# Systemd service 化
/etc/systemd/system/claude-deploy.service
  ExecStart=/usr/local/bin/claude --apikey $ANTHROPIC_API_KEY 
           "deploy to sv6810: ssh -i ~/.ssh/sv6810_key user@sv6810.wpx.ne.jp ..."

# または cron で定期実行、あるいは任意トリガーで実行
*/30 * * * * /usr/local/bin/claude "deploy to sv6810..." 2>&1 >> /var/log/claude-deploy.log
```

**利点**：PC 完全依存なし、鍵管理が VM に集約、Web UI / CLI から柔軟に操作可能

---

### ケース C：「短期・小規模」

**Claude Code on the web（Web UI）+ SessionStart hook**

- GitHub 連携 → `claude.ai/code` で手動トリガー
- SessionStart で SSH キーを一時的に注入（セキュリティ要注意）
- ネットワーク許可設定で sv6810 への接続を明示許可

**注意**：
- Anthropic sandbox のセキュリティポリシー上、SSH キーを埋め込むことは推奨されない可能性がある
- 本番運用には不向き

---

## 結論

| 方式 | 実現性 | セキュリティ | PC依存 | 運用難度 | 推奨 |
|-----|-------|----------|--------|--------|------|
| Claude Code on the web | △ | ✗ | ◎ | △ | 小規模テスト |
| GitHub Actions | ◎ | ◎ | ◎ | ◎ | **推奨（CI/CD統合時）** |
| VPS + Claude CLI 常駐 | ◎ | ◎ | ◎ | ◎ | **推奨（自由度重視）** |
| Sandbox / Devcontainer | ✗ | - | ✗ | ✗ | 非推奨 |

**最優先：VPS（小型インスタンス月500円程度）上に Claude CLI を常駐化し、SSH 鍵を VM 内管理する方式が最もシンプルで堅牢。** GitHub Actions はリポジトリ・PR ベースの自動デプロイ（例：main マージ後の自動本番反映）に適している。

## 追記：Claude Code Routines 機能

2026-05-08 に追加調査。

### Routines とは

- 2026年4月公開の新機能（Research Preview）。Anthropic 管理クラウドで動く自動化レイヤー
- プロンプト + リポジトリ + MCP 接続をパッケージ化し、**スケジュール / API / GitHubイベント**でトリガー可能
- 物理 PC 完全非依存。シークレットは環境変数で保管

### デプロイ用途で実際に使われているか（実例調査）

**結論：本番 SSH デプロイの実例は事実上ゼロ。"周辺自動化"の事例のみ。**

- 公式・解説記事は「CD パイプラインがデプロイ完了後に Routines API を叩き、スモークテスト・ヘルスチェック・Slack 通知」というレシピを推奨
- 実例として確認できたのはすべて **デプロイ後の検証層** または **PR レビュー / issue トリアージ / 週次ドキュメント検査**
- 「Routines から SSH してデプロイ」構成は設計上向かない（クラウドサンドボックス + 権限継承リスク）

### 落とし穴

| 項目 | 内容 |
|------|------|
| 権限継承 | Routines は作成者のフル権限を継承し、実行中に承認プロンプトなし。SSH 秘密鍵を渡すのはハイリスク → deploy key / 短命証明書（Vault / Teleport）推奨 |
| 日次実行上限 | Pro 5/日, Max 15/日, Team/Enterprise 25/日 |
| ブランチ制約 | 初期設定で `claude/` プレフィックスへの push 限定 |
| プロンプト肥大 | Skill 化していないと運用後デバッグ困難。Skill → Routines の順 |
| 増えすぎ問題 | 作成は容易、廃止は手動。週次レビュー運用が推奨されている |

### 現実解（再評価）

本プロジェクト（hpkenkyu → av-hakase.com SSH デプロイ）の用途では、Routines 単体での本番デプロイは推奨されない。以下の分業が定型：

- **(a) GitHub Actions + SSH アクション**：実デプロイ本体
- **(b) Routines**：デプロイ後のスモークテスト・ヘルスチェック・Slack 通知

VPS 常駐方式（前述）の優位性は変わらず。Routines は将来的に CD 補助レイヤーとして検討する価値あり。

## 追記：VPS常駐 vs Routines のリスク比較

### 観点別比較

| 観点 | VPS常駐 | Routines |
|------|---------|----------|
| 鍵の保管場所 | 自前VMのディスク（自分の管理責任） | Anthropic環境変数（プロバイダ依存） |
| OS脆弱性 | 自分でパッチ運用が必要（fail2ban / SSH brute force対策含む） | なし（Anthropic管理） |
| 権限の暴発 | Claudeに渡したシェル権限はVM内に閉じる | 作成者のフル権限を継承し**実行中の承認プロンプトなし** → プロンプトインジェクションが直ヒット |
| 監査ログ | 自分でロギング設定が必要 | Anthropic側UIで履歴が残る |
| ネットワーク経路 | VPS → 本番（IP直結、ファイアウォール自前） | Anthropic IP → 本番（許可リスト要、送信元IP固定不可な場合あり） |
| 可用性 | VPSベンダ障害 + 自前tmuxプロセス管理 | Anthropic障害 + Research Preview仕様変更 |
| コスト | 月500〜1000円固定 | プラン込み + API課金、日次上限 |
| インシデント時の遮断 | `iptables` / 鍵削除で即遮断可能 | Anthropic UI経由のみ |

### 性質の違い

- **VPS常駐**：「自分で全部やる」型。リスクが**自分の運用品質に比例**する。手を抜くと脆弱、ちゃんとやれば堅牢
- **Routines**：「Anthropicに乗っかる」型。OSパッチから解放される代わりに、**プロンプトインジェクション耐性とプロバイダ依存リスク**を肩代わり。承認プロンプトなしでフル権限実行のため、Claudeが外部入力（PR本文・issueコメント・MCP応答）に騙されたときの被害が大きい

### 実務推奨順

1. **最低リスク**：GitHub Actions に SSH鍵を置き、tag push 等の明示トリガーでデプロイ。Claudeはその外側
2. **中**：VPS常駐 + deploy key のみ（書き込み権限を最小化）+ コマンド実行ログを別ホストに転送
3. **高**：Routines で本番直叩き（推奨されていない）

## 追記：4方式の簡易比較サマリー

### A: PC依存OK — Claude Code スケジュール plugin（`/loop` 等）

ローカルClaude Codeセッション内でスケジュール起動。物理PCが起動・ネット接続中である必要あり。

| 項目 | 内容 |
|------|------|
| PC依存 | あり（PCを閉じると停止） |
| 認証 | Pro/Maxサブスク（OAuth） |
| 追加コスト | サブスク内で完結 |
| セキュリティ | ローカル鍵管理、外部露出最小 |
| 向く用途 | 開発中の試行錯誤、軽い定期チェック |

### B-1: claude-code-action（GitHub Actions）

PR/push/scheduleで起動。エフェメラル実行。

| 項目 | 内容 |
|------|------|
| PC依存 | なし |
| 認証 | **API key 必須**（OAuthはToS違反） |
| 追加コスト | API従量、Sonnetで月$5〜15程度（30〜100デプロイ） |
| セキュリティ | ◎ 鍵は Secrets 暗号化、毎回clean、監査ログあり |
| 弱点 | 送信元IP不定、Anthropic+GitHub二重依存、コールドスタート |
| 向く用途 | チーム運用、PR連動デプロイ、監査が必要な本番 |

### B-2: VPS常駐 Claude Code

VPS上で `claude login`（Pro/Max OAuth）+ tmux常駐 or cron起動。

| 項目 | 内容 |
|------|------|
| PC依存 | なし |
| 認証 | Pro/Maxサブスク（OAuth）or API key |
| 追加コスト | VPS月$5〜10 + Pro $20 = サブスク込み |
| セキュリティ | △ 鍵が常時ディスク上、自前でOS保守 |
| 強み | 送信元IP固定（FW許可容易）、コスト予測性、ベンダロックイン低 |
| 向く用途 | 個人運用、コスト最優先、副次的にバッチ処理も同居 |

### B-3: cron + Anthropic API 直叩き

Claude Codeを使わず、シェルスクリプトから `curl` でAnthropic APIを呼ぶ最小構成。デプロイロジックはスクリプト側に書く。

| 項目 | 内容 |
|------|------|
| PC依存 | なし |
| 認証 | API key |
| 追加コスト | API従量、用途を絞れば月$1未満も可能 |
| セキュリティ | ◎ Claudeに与える権限を最小化（特定エンドポイント呼出のみ） |
| 強み | 最も軽量・予測可能・デバッグ容易 |
| 弱点 | Claudeが「自律的に動く」要素なし。エージェントではなく単なるLLM呼出。MCP等は使えない |
| 向く用途 | 「文章生成→デプロイ」のような単発タスク、定型処理 |

### 一覧

| 方式 | PC依存 | 認証 | 月額目安 | セキュリティ | 自律性 |
|------|:---:|------|--------|:---:|:---:|
| A: スケジュールplugin | あり | Pro/Maxサブ | サブ込 | ◎ | 高 |
| B-1: GitHub Actions | なし | API key | $5〜15 | ◎ | 高 |
| B-2: VPS常駐 | なし | Pro/Max or API | $5〜30 | △ | 高 |
| B-3: cron + API直叩き | なし | API key | $1未満〜 | ◎ | 低 |

### 一言推奨

- **動作確認・試行錯誤フェーズ**: A
- **本格運用 / チーム展開**: B-1
- **個人で安く長期運用**: B-2
- **デプロイ手順が完全に定型化済み**: B-3

## 参考資料

- [Claude Code on the web - code.claude.com](https://code.claude.com/docs/en/claude-code-on-the-web.md)
- [GitHub Actions - code.claude.com](https://code.claude.com/docs/en/github-actions.md)
- [Hooks reference - code.claude.com](https://code.claude.com/docs/en/hooks.md)
- [Claude Code SessionStart Hook Guide](https://claudefa.st/blog/tools/hooks/session-lifecycle-hooks)
- [Introducing routines in Claude Code (公式)](https://claude.com/blog/introducing-routines-in-claude-code)
- [Claude Code Routines Docs (JA)](https://code.claude.com/docs/ja/routines)
- [Claude Code Routines: 5 Production Workflows + MCP Setup (Arcade.dev)](https://www.arcade.dev/blog/claude-code-routines-mcp-setup/)
- [Claude Code Routines: 8 Production Prompts (linas.substack)](https://linas.substack.com/p/claude-code-routines-guide)
- [【実録】Claude Code Routines を3日間運用してみた (Qiita @nogataka)](https://qiita.com/nogataka/items/3ff7b14684306ef413e0)
- [How Allowing Claude to Access My Server via SSH (Zenn @kitepon)](https://zenn.dev/kitepon/articles/claude-code-ssh-deploy?locale=en)
- [A better way to limit Claude Code access to Secrets (patrickmccanna.net)](https://patrickmccanna.net/a-better-way-to-limit-claude-code-and-other-coding-agents-access-to-secrets/)
- [Claude Code Security Best Practices (Backslash)](https://www.backslash.security/blog/claude-code-security-best-practices)

