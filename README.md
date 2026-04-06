# laravel-multitenant-saas

![CI](https://github.com/Shaisolaris/laravel-multitenant-saas/actions/workflows/ci.yml/badge.svg)

Laravel 11 multi-tenant SaaS platform with tenant isolation, Stripe Cashier billing, role-based access control (Spatie), team management, API key authentication with scoped permissions, usage tracking with rate limiting, and member invitations. Four-tier plan system with configurable limits.

## Stack

- **Framework:** Laravel 11, PHP 8.2+
- **Auth:** Laravel Sanctum (token-based API auth)
- **Billing:** Laravel Cashier (Stripe subscriptions, invoices, customer portal)
- **RBAC:** Spatie Laravel Permission (roles and permissions)
- **Tenancy:** Custom tenant isolation via middleware and model scopes
- **Database:** MySQL/PostgreSQL with proper foreign keys and indexes

## Architecture

```
app/
├── Http/
│   ├── Controllers/Api/
│   │   ├── AuthController.php        # Register (with tenant provisioning), login, logout, me
│   │   ├── TenantController.php      # Tenant CRUD, members, invitations, billing
│   │   ├── TeamController.php        # Team CRUD, member add/remove
│   │   └── ApiKeyController.php      # API key generation, listing, revocation
│   └── Middleware/
│       ├── EnsureTenantAccess.php    # Validates user has active tenant, binds to container
│       └── AuthenticateApiKey.php    # X-API-Key header auth with scope checking + rate limiting
├── Models/
│   ├── Tenant.php                    # Billable (Cashier), plan limits, settings, status
│   ├── User.php                      # HasRoles (Spatie), HasApiTokens (Sanctum), tenant scope
│   ├── Team.php                      # Tenant-scoped, many-to-many users with pivot role
│   ├── ApiKey.php                    # Scoped permissions, expiration, usage tracking
│   ├── UsageRecord.php              # Per-metric usage tracking with aggregation queries
│   └── Invitation.php               # Token-based invitations with expiration
├── Services/
│   └── TenantService.php            # Provisioning, plan upgrades, usage summary, deactivation
├── Traits/
├── Events/
├── Listeners/
└── Exceptions/

config/
└── tenancy.php                       # 4-tier plan config with limits and Stripe price IDs

database/migrations/
├── create_tenants_table              # Tenant with Cashier columns, plan, status, settings
├── add_tenant_to_users               # tenant_id FK, avatar, timezone, status on users
├── create_teams_table                # Teams + team_members pivot with role
├── create_api_keys_table             # Scoped API keys with expiration and usage tracking
├── create_usage_records_table        # Per-tenant, per-metric usage with timestamps
└── create_invitations_table          # Email invitations with token and expiration

routes/
└── api.php                           # Public auth + Sanctum-protected + API key routes
```

## API Endpoints

### Authentication
| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/auth/register` | Register + auto-provision tenant |
| POST | `/api/auth/login` | Login, returns Sanctum token |
| POST | `/api/auth/logout` | Revoke current token |
| GET | `/api/auth/me` | Current user + tenant |

### Tenant Management
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/tenant` | Tenant details + usage + limits |
| PATCH | `/api/tenant` | Update tenant name/settings |
| GET | `/api/tenant/members` | Paginated member list |
| POST | `/api/tenant/invite` | Invite member by email + role |
| DELETE | `/api/tenant/members/{id}` | Remove member |
| GET | `/api/tenant/billing` | Plan, subscription, invoices |

### Teams
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/teams` | List teams with members |
| POST | `/api/teams` | Create team (checks plan limit) |
| GET | `/api/teams/{id}` | Team detail with members |
| POST | `/api/teams/{id}/members` | Add member to team |
| DELETE | `/api/teams/{id}/members/{userId}` | Remove member from team |
| DELETE | `/api/teams/{id}` | Delete team |

### API Keys
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/api-keys` | List API keys (key value hidden) |
| POST | `/api/api-keys` | Generate new key (shown once) |
| POST | `/api/api-keys/{id}/revoke` | Revoke key |

### API Key Routes (v1)
| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/api/v1/tenant` | X-API-Key | Tenant info via API key |
| GET | `/api/v1/teams` | X-API-Key | Teams via API key |

## Multi-Tenancy Model

Tenant isolation is enforced at multiple layers:

1. **Middleware** (`EnsureTenantAccess`) validates user has a tenant and it's active, binds tenant to the service container
2. **Model scopes** (`scopeForTenant`) filter all queries by `tenant_id`
3. **Controller checks** verify resources belong to the current tenant before any operation
4. **API key middleware** resolves tenant from the key and applies the same isolation

The tenant owner cannot be removed. Plan limits are checked before creating users, teams, and API keys.

## Plan Limits

| Resource | Free | Starter | Pro | Enterprise |
|---|---|---|---|---|
| Users | 3 | 10 | 50 | Unlimited |
| Teams | 1 | 5 | 20 | Unlimited |
| API Calls/mo | 1,000 | 50,000 | 500,000 | Unlimited |
| Storage | 100 MB | 5 GB | 50 GB | Unlimited |
| Price | $0 | $29/mo | $99/mo | $299/mo |

## Usage Tracking & Rate Limiting

Every API call through the API key middleware is recorded in the `usage_records` table. The middleware checks current month usage against the plan's `api_calls` limit. When the limit is exceeded, the API returns 429 with `X-RateLimit-Limit`, `X-RateLimit-Remaining`, and `X-RateLimit-Reset` headers.

Usage is tracked per-tenant, per-metric, with timestamps for period-based aggregation. The `TenantService::getUsageSummary()` method returns current usage vs limits as percentages.

## Setup

```bash
git clone https://github.com/Shaisolaris/laravel-multitenant-saas.git
cd laravel-multitenant-saas
composer install
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate
php artisan db:seed

# Stripe (for billing)
# Add STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET to .env

php artisan serve
```

## Key Design Decisions

**Custom tenancy over stancl/tenancy for data isolation.** While stancl/tenancy is listed as a dependency for advanced features, the core isolation uses a simpler `tenant_id` foreign key pattern with model scopes and middleware. This is more predictable than database-per-tenant for most SaaS applications and avoids the complexity of tenant-aware migrations.

**Dual auth: Sanctum tokens + API keys.** User-facing routes use Sanctum bearer tokens (login session). External API routes use `X-API-Key` header authentication with scoped permissions. This separates human auth from machine auth, each with appropriate security characteristics.

**Tenant provisioning on registration.** Every new user automatically gets a tenant. There's no "create workspace" step. This reduces onboarding friction. The user becomes the tenant owner with full permissions.

**Plan limits enforced at creation time.** The system checks `hasReachedLimit()` before creating users, teams, or API keys. It does not retroactively enforce limits on existing resources when a tenant downgrades — that's handled by the billing webhook.

**Usage records as append-only log.** Usage is recorded as individual timestamped records rather than counters. This enables per-period aggregation, audit trails, and billing integration. The `getUsage()` method sums quantities within a time range.

## License

MIT
