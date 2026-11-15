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

- ブラウザで `http://localhost:8080` を開く（未ログイン時は Google ログインへリダイレクト）

## 認証

- 画面: セッション認証
  - Google Auth Platform でウェブアプリケーション用 OAuth クライアントを作成する
  - 承認済みリダイレクト URI にローカル用 `http://localhost:8080/auth/google/callback` を登録する
  - 本番では実際の HTTPS URL の `/auth/google/callback` を登録する
  - `.env` に `APP_GOOGLE_CLIENT_ID`、`APP_GOOGLE_CLIENT_SECRET`、`APP_GOOGLE_REDIRECT_URI`、`APP_GOOGLE_ALLOWED_SUB` を設定する
  - 初回は `APP_GOOGLE_ALLOWED_SUB` を空にして Google ログインを試し、拒否画面に表示される本人の `sub` を設定する。設定後にアプリを再起動する
  - クライアント ID、シークレット、リダイレクト URI が未設定の場合は認証を開始しない。許可する `sub` が未設定の場合は、Google 認証後もログインを拒否する
  - 旧方式のセッションは引き継がれないため、切り替え後は Google ログインが必要
  - 未ログイン時は `/auth/google` から Google の認証画面へ移動する。ログアウト後は `/` を開いても `/login` を表示し、再ログインはボタン操作で開始する
  - 認証失敗時も `/login` を表示する
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
