# Laravel MySQL Replication

A Laravel 13 demo of **MySQL 8.4 primary/replica replication** with **read/write splitting**. Writes go to one primary. Reads are load-balanced across two read-only replicas. MySQL copies the `master_db` database using GTID replication; Laravel only chooses which server to query.

This is a local learning environment, not a production deployment. Default passwords are committed for convenience.

This topology is one writer and two readers (sometimes described as one master and two slaves). It is native MySQL replication, not Vitess.

## Architecture

```
                         ┌─────────────────────┐
                         │   Laravel app       │
                         │   :8000             │
                         └──────────┬──────────┘
                    INSERT/UPDATE   │   SELECT (random replica)
                                    │
               ┌────────────────────┼────────────────────┐
               ▼                    ▼                    ▼
     ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
     │ Primary (write) │  │ Replica 1 (read)│  │ Replica 2 (read)│
     │ :3310  id=1     │─►│ :3311  id=2     │  │ :3312  id=3     │
     │ read_only = OFF │─►│ read_only = ON  │  │ read_only = ON  │
     └─────────────────┘  └─────────────────┘  └─────────────────┘
               ▲                    ▲                    ▲
               └──────── phpMyAdmin :8080 ───────────────┘
```

| Role | Host port | MySQL `@@server_id` | Writable |
| --- | --- | --- | --- |
| Primary | `127.0.0.1:3310` | `1` | Yes |
| Replica 1 | `127.0.0.1:3311` | `2` | No |
| Replica 2 | `127.0.0.1:3312` | `3` | No |

All three servers use the same database name: **`master_db`**. Each replica is a copy of the primary, not a second schema.

## Requirements

- PHP 8.3+ with `pdo_mysql`
- Composer
- Docker and Docker Compose
- Node.js (for the dashboard assets)

Ports `3310`, `3311`, `3312`, and `8080` must be free on the host.

## Quick start

```bash
cp .env.example .env
php artisan key:generate

docker compose up -d
php artisan migrate
npm install
npm run build
php artisan serve
```

Wait until all three MySQL containers are healthy (`docker compose ps`) before migrating. First boot can take a minute or two.

Confirm replication:

```bash
php artisan replication:status
```

You should see IO/SQL threads `Yes`, lag `0`, primary server id `1`, replica ids `2` and `3`.

| Service | URL |
| --- | --- |
| Dashboard | http://127.0.0.1:8000 |
| phpMyAdmin | http://127.0.0.1:8080 |
| API | http://127.0.0.1:8000/api |

## Configuration

Application connection settings live in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3310
DB_DATABASE=master_db
DB_USERNAME=laravel
DB_PASSWORD=secret

DB_REPLICA_HOST=127.0.0.1
DB_REPLICA_PORT=3311
DB_REPLICA_2_HOST=127.0.0.1
DB_REPLICA_2_PORT=3312
DB_STICKY=true
```

Laravel read/write splitting is defined in `config/database.php`. When `DB_REPLICA_HOST` is set, the default `mysql` connection uses:

- `write` → primary (`DB_HOST` / `DB_PORT`)
- `read` → replica 1 and replica 2 (Laravel picks one at random per request)
- `sticky` → after a write in the same request, later reads in that request use the primary so you do not read a row the replica has not copied yet

Named connections `mysql_primary`, `mysql_replica`, and `mysql_replica_2` always target one server. The dashboard uses them to compare row lists.

`@@server_id` is the reliable marker for which process you hit: **1 = write**, **2 or 3 = read**.

## How replication is established

`docker-compose.yml` starts three MySQL 8.4 instances with distinct `server-id`s, GTID, and two read-only replicas. That alone does **not** connect them.

Init scripts mounted into `/docker-entrypoint-initdb.d` run once on first volume create:

| Script | Purpose |
| --- | --- |
| `docker/mysql/primary/init/01-replication-user.sh` | Creates the `repl` user with `REPLICATION SLAVE` |
| `docker/mysql/replica/init/01-start-replication.sh` | `CHANGE REPLICATION SOURCE` + `START REPLICA` (shared by both replicas) |

`--replicate-do-db=master_db` must match `MYSQL_DATABASE` and `DB_DATABASE`. Changing the database name in Compose does not rename an existing volume; grants and the replica filter must be updated as well.

## HTTP APIs

API routes (`routes/api.php`) do not use CSRF. Use them from Postman.

| Method | Path | Behavior |
| --- | --- | --- |
| `GET` | `/api/replication/status` | Primary/replica identity, lag, `select_uses_replica` |
| `GET` | `/api/posts` | List posts (SELECT → a replica) |
| `POST` | `/api/posts` | Create a post (INSERT → primary) |
| `GET` | `/api/posts/{id}` | Show one post (SELECT → a replica) |

Create a post:

```http
POST http://127.0.0.1:8000/api/posts
Accept: application/json
Content-Type: application/json

{
  "title": "From Postman",
  "body": "Written to the primary."
}
```

The JSON body includes `written_to.server_id` on create and `read_from.server_id` on reads.

The browser dashboard at `/` still uses session CSRF. `POST /posts` from Postman without a token returns **419 Page Expired**. Use `/api/posts` instead.

## phpMyAdmin

Open http://127.0.0.1:8080 and pick the server dropdown:

- **primary-write** — writable source
- **replica-1-read** — read-only copy (`:3311`)
- **replica-2-read** — read-only copy (`:3312`)

Login: `laravel` / `secret`. Inspect `master_db` → `posts` on all three. Inserts on a replica should fail. Inserts on **primary-write** (or via the API) should appear on both replicas shortly after.

## Project layout

```
docker-compose.yml
docker/mysql/primary/init/     # replication user
docker/mysql/replica/init/     # START REPLICA (both replicas)
config/database.php            # read / write / sticky
app/Http/Controllers/Api/      # Postman APIs
app/Services/ReplicationMonitor.php
routes/web.php                 # dashboard
routes/api.php                 # /api/*
```
