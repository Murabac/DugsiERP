# School ERP Control Plane — Plan

> Working name for the **developer / operator console** that provisions and manages many school ERP tenants (including Dugsi ERP instances).  
> This is **not** the school-facing product. Schools use Dugsi ERP; you use the Control Plane.

---

## Recommendation (short)

**Do both layers — do not put school letterheads inside the Control Plane UI.**

| Layer | Owns | Shows on printables? |
|---|---|---|
| **Dugsi ERP (tenant app)** | Day-to-day school ops + `school_settings` (name, logo, location) | **Yes — school name** |
| **Control Plane (your system)** | Tenant lifecycle, billing, versions, support access, pushing seed config | No — operators only |

**Why this is better than only a central system:**

1. Printables must work offline / when the control plane is down — identity lives in the tenant DB.
2. Each school already has Super Admin / Admin who may correct the legal school name without waiting on you.
3. The Control Plane can still **set or lock** the school name at provisioning time (and optionally re-sync).
4. Product brand (**Dugsi ERP**) stays on login/sidebar; documents always use **school profile**.

Avoid a design where every PDF calls home to fetch the school name. Push config down; store it locally.

---

## What we already did in Dugsi ERP

- `school_settings` keys: `school_name`, `school_tagline`, `school_location`
- Printables (grade report, attendance register, timetable) + on-screen report letterhead use those values
- Settings → **School** tab lets Admin / Super Admin edit the profile
- Default seed: **Qudus Secondary School** · Secondary School · Somaliland

Later Control Plane work should write these same keys (or a signed `tenant.json`) when creating a school.

---

## Control Plane — product scope

### Goals

- Create / suspend / archive school tenants
- Assign package (modules: fees, payroll, SMS, etc.)
- Push school identity (name, logo URL, location, timezone, currency)
- Track which Dugsi ERP version each tenant runs
- Support “impersonate / support login” with audit trail
- Billing & invoices for the school (B2B), separate from in-school fee collection

### Non-goals (v1)

- Replacing in-app Settings for teachers/admins
- Editing student/grade data from the Control Plane (use support access into the tenant instead)
- Multi-school single database without isolation (prefer strong tenant boundaries)

---

## Suggested architecture

```
┌─────────────────────────────────────────────┐
│  Control Plane (Laravel / separate repo)    │
│  - tenants, plans, deploys, support users   │
│  - API + web console for Murabac developers │
└──────────────────┬──────────────────────────┘
                   │ provision / sync config
                   ▼
┌─────────────────────────────────────────────┐
│  Tenant: Dugsi ERP instance                 │
│  - own DB (or schema)                       │
│  - school_settings.school_name = …          │
│  - printables read local settings only      │
└─────────────────────────────────────────────┘
```

### Tenant isolation options (pick one later)

| Option | Pros | Cons |
|---|---|---|
| **A. DB-per-school** | Strong isolation, easy backup/restore | More ops |
| **B. Schema-per-school** | Cheaper than full DB | Migrations more careful |
| **C. Shared DB + `tenant_id`** | Simple hosting | Harder compliance / noisy-neighbor |

**Recommendation for Somaliland school SaaS:** start with **A or B**. Shared-row tenancy (C) is fine only if you invest early in row-level discipline and backups.

### Config sync contract (v1)

Control Plane → Tenant (HTTPS, signed):

```json
{
  "tenant_id": "qudus-hargeisa",
  "school_name": "Qudus Secondary School",
  "school_tagline": "Secondary School",
  "school_location": "Hargeisa, Somaliland",
  "logo_url": "https://cdn…/qudus.png",
  "modules": ["attendance", "grades", "fees"],
  "grade_edit_window_days": 5
}
```

Tenant endpoint (Super Admin / machine token only): `POST /internal/tenant-config`  
Idempotent upsert into `school_settings` (+ future `school_logos`).

Optional flag: `lock_school_name: true` so local admins cannot rename (enterprise / franchise cases).

---

## Phased roadmap

### Phase 0 — Now (Dugsi ERP) ✅ / near-term

- [x] School profile settings + printables use school name
- [ ] Logo upload for letterhead
- [ ] SMS / email footers use school name (not product name)
- [ ] Document module (certificates, receipts) share one letterhead partial

### Phase 1 — Control Plane MVP

- Tenant registry (name, domain/subdomain, status)
- Manual “create tenant” → provision empty Dugsi ERP + seed Super Admin
- Push school profile JSON once
- List tenants + open support notes

### Phase 2 — Operations

- Version / deploy tracking
- Suspend tenant (read-only or login blocked)
- Support impersonation with mandatory reason + log
- Health checks (last backup, queue, disk)

### Phase 3 — Commercial

- Plans & module entitlements
- Invoicing schools for SaaS subscription
- Usage metrics (students active, SMS sent) for pricing

---

## Naming

| Audience | Name |
|---|---|
| School users | **Dugsi ERP** (or white-label later) |
| Your developers | **School ERP Control Plane** / **Murabac School Console** |
| On paper / PDFs | **Always the school’s legal name** |

Do not brand report cards as Dugsi ERP even after the Control Plane exists.

---

## Open decisions (ask before building Phase 1)

1. Hosting: one VPS many containers, or Laravel Cloud / Forge sites per school?
2. Domains: `qudus.dugsi.app` vs custom `erp.qudus.edu.so`?
3. Who may edit school name after go-live: school Admin, only Control Plane, or both?
4. Same codebase for all schools (config-only) vs forks?

Default answers if unspecified: **shared codebase + per-tenant DB + both can edit name unless locked**.
