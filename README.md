# kizami

## ローカルセットアップ

1. 環境変数ファイルを作成

```bash
cp .env.example .env
```

2. コンテナ起動

```bash
docker compose up -d
```

3. 依存パッケージをインストール

```bash
docker compose run --rm --no-deps app composer install
```

4. マイグレーション実行（初期データも登録されます）

```bash
docker compose run --rm app php bin/doctrine migrations:migrate -n
```

5. アプリ確認

- ブラウザで `http://localhost:8080` を開く（未ログイン時は `/login` へリダイレクト）

## 認証

- 画面: セッション認証
  - `.env` の `APP_ADMIN_USERNAME` / `APP_ADMIN_PASSWORD_HASH` を利用
  - `APP_ADMIN_PASSWORD_HASH` は `password_hash` で生成したハッシュ文字列
    - 生成例: `php -r "echo password_hash('your-password', PASSWORD_DEFAULT) . PHP_EOL;"`
  - いずれか未設定（空含む）の場合、ログイン不可
  - ログイン試行制限（デフォルト: 5回失敗で10分ロック）
    - `APP_AUTH_MAX_ATTEMPTS`
    - `APP_AUTH_WINDOW_SECONDS`
    - `APP_AUTH_LOCK_SECONDS`
  - セッションCookie設定
    - `APP_SESSION_COOKIE_SECURE`
    - `APP_SESSION_COOKIE_SAMESITE`
- API: API キー認証
  - `Authorization: Bearer <API_KEY>` ヘッダを利用
  - API キーは `api_keys` テーブルでハッシュ（SHA-256）管理
  - 開発用初期キー: `dev-api-key-change-me`

## コードチェック

- `php-cs-fixer`（整形/フォーマット）
- `phpstan`（静的解析）
- `phpunit`（テスト）

```bash
docker compose run --rm --no-deps app composer cs:check
docker compose run --rm --no-deps app composer stan
docker compose run --rm --no-deps app composer test
```

まとめて実行:

```bash
docker compose run --rm --no-deps app composer check
```

## UIレイアウト

- 軽量構成として `Pico.css` を CDN で読み込み（`templates/layout/base.html.twig`）

## API

- `GET /api/v1/reports/hours?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD`
- レスポンス: 期間内のクライアント別稼働時間合計

例:

```bash
curl -H "Authorization: Bearer dev-api-key-change-me" \
  "http://localhost:8080/api/v1/reports/hours?date_from=2026-02-01&date_to=2026-02-28"
```

## 補足

- `migrations/Version20260212203000.php` で以下を作成します
  - `clients`
  - `work_categories`（初期データ: 設計作業/開発作業/コーディング/インフラ/打ち合わせ/その他）
  - `time_entries`
- `migrations/Version20260212230000.php` で `api_keys` を作成します
