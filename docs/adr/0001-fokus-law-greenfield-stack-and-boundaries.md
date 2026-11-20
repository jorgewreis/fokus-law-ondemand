# ADR-0001: Fokus Law Greenfield stack and environment boundaries

- Status: Accepted
- Decision owner: Jorge, developer
- Decision date: 2026-11-18
- Recorded in this repository: 2026-11-19
- Scope: Synthetic MVP for Fokus Cloud Law — 1ª Vara Crime de Ilhéus
- Source decisions: [Linear REI-195](https://linear.app/reis-developer/issue/REI-195/vara-definir-arquitetura-greenfield-e-fronteiras-dos-ambientes), the approved [PRD](https://app.notion.com/p/41b6af4c510981e0b980eff10759823a) (sections 1, 4, 5, 7, 8, 9 and 10.1), and current product scope in [Linear REI-206](https://linear.app/reis-developer/issue/REI-206/vara-documentar-funcionalidades-e-regras-dos-modulos)

## Context

The product is a Greenfield application for the synthetic MVP. The approved scope has one developer, a server-rendered application, and no automatic migration of code or data from legacy systems. The authorized KVM1 is shared with existing production services, so synthetic co-hosting requires logical separation and evidence before activation. Institutional data, production use, PJe integration, and institutional homologation are outside this decision.

The current product scope in the PRD and REI-206 defines the synthetic MVP as Pessoa → Processo; Gestão de Expedições is for a subsequent phase and is outside MVP acceptance. Older workflow references that include Expedição are historical and superseded for MVP scope. This clarification aligns product-scope references only; it does not change the approved architecture decision or authorize deployment. Identity, authorization, audit, storage, and backup boundaries remain explicit. Actual host configuration, logical application separation, and end-to-end recovery are separate verification gates.

As observed on 2026-11-19, the VPS reports PHP 8.5.12, Percona Server for MySQL 8.4.11, and CloudPanel 6.0.8. Effective application configuration and InnoDB availability were not fully verified. At the edge, law.fokuscloud.com.br is redirected by a Cloudflare rule before reaching the origin; the current origin certificate does not include the law hostname and no Law vhost was observed. A Docker-published TCP 3333 listener for the existing Fokus Lead service was externally reachable despite UFW's default-deny policy; its purpose remains unconfirmed. The user chose to keep that mapping unchanged for now. These are dated observations, not mitigations or authorization to change shared routing.

## Options considered

### 1. Modular Laravel monolith with server-rendered Blade — selected

- Pros: one application and runtime to build and operate; fits the PHP/Laravel hosting already selected; avoids a separate frontend/API boundary for the MVP.
- Cons: module boundaries and server-side authorization must be enforced within one application; a shared host still shares operating-system, resource, availability, and recovery failure domains.

### 2. Django 5.2 LTS with PostgreSQL

- Pros: mature framework with built-in authentication, groups, and permissions.
- Cons: introduces a second framework ecosystem for this product and still requires a separate MFA integration and hosting compatibility work.

### 3. Separate SPA and API

- Pros: separates frontend deployment from API implementation and permits independent clients.
- Cons: adds an API contract, deployment surface, and authentication boundary without a required MVP client or workflow that benefits from the split.

The approved REI-195 decision explicitly records the selected stack and the Django and SPA/API alternatives. The pros and cons above are architecture analysis recorded in this ADR; they should not be read as additional product approval.

## Decision

Use a modular monolith in Laravel 13 and PHP 8.5, with MySQL 8.4 LTS using InnoDB and Blade-rendered pages. Keep modules within the application; do not create separate frontend/API services for this MVP. The project is Greenfield and does not automatically migrate legacy code or data.

For synthetic development, use individual accounts with MFA and server-side authorization that denies ungranted modules and actions by default. Self-registration remains pending and grants no functional access. Only Jorge approves test accounts and explicitly grants modules and action sets. The server must enforce the user's applicable unit, competence, and secrecy scope; these dimensions are not additional grants made by this decision. Login, invitation, and TOTP setup alone do not grant access. Support autonomy does not grant account-approval or module/action-provisioning powers.

### Initial synthetic administrator

Create the first configured synthetic administrator through the one-time CLI bootstrap in [REI-235](https://linear.app/reis-developer/issue/REI-235/vara-criar-bootstrap-cli-de-uso-unico-para-a-conta-administrativa), as recorded in the approved [PRD](https://app.notion.com/p/41b6af4c510981e0b980eff10759823a). Supply the secret token outside the repository and accept it only through a hidden prompt; keep only its SHA-256 verifier in external configuration and consume the bootstrap atomically once. Do not seed a default account or password. The bootstrap creates no session, MFA enrollment, functional grant, or access to a module. The bootstrap is the explicit creation step for this initial account; subsequent accounts still require separate approval, MFA, and explicit module/action grants. The administrator marker does not itself authorize access. This decision applies only to the local synthetic environment and does not authorize real accounts, production access, or deployment.

The synthetic environment may share the KVM1 with existing production only after the developer approves activation and the controls in PRD section 10.1 are evidenced. This is logical separation, not infrastructure isolation. The application must use dedicated site directories and PHP-FPM pool, MySQL database and user, credentials, keys, sessions, and storage; contain no production data, secrets, or integrations; and pass negative cross-product access tests. No remote deployment or activation is authorized by this ADR.

For the synthetic MVP, backups are application-specific, encrypted before transfer, and sent to the separate local workstation over a protected outbound-initiated path. The approved monthly process, retention, key separation, capacity checks, and restore acceptance remain governed by PRD section 8.3. This backup decision does not apply to real or production data.

## Consequences

- The first release can be built and tested as one Laravel application with a relational schema and server-rendered UI.
- Functional module, unit, secrecy, and record-level authorization must be enforced in server-side code and verified with positive and negative tests.
- Shared KVM1 resources and failure domains remain a material risk; the approved synthetic environment cannot be activated until logical isolation, network exposure, capacity, and recovery gates pass.
- The current bootstrap and CI establish only a local development baseline. They do not demonstrate the workflow, complete authorization, KVM1 isolation, production readiness, or backup/restore.
- Later requirements for institutional identity, real data, production policy, retention, RPO/RTO, or external integration require separate authorization and a superseding decision where they change this architecture.

## Verification record

- Options and approved selection: Django and SPA/API are named as considered alternatives in REI-195; detailed pros/cons and consequences here are architecture analysis, not separate user approvals.
- Account governance and scopes: cross-checked against the current PRD on 2026-11-19; only test-account approval and module/action grants are attributed to Jorge, while unit/competence/secrecy checks remain server-side enforcement.
- Current hosting observations: PHP 8.5.12, Percona 8.4.11, CloudPanel 6.0.8; MySQL engine and Law vhost configuration remain unverified. Cloudflare redirect/certificate observations and externally reachable Docker TCP 3333 were recorded as current state, not proof of mitigation.
- Synthetic fixtures are wholly fictitious and not derived from masked copies.
- Current scope cross-check (2026-11-19): PRD sections 4 and 5 and REI-206 place Gestão de Expedições in a later phase; MVP acceptance follows Pessoa → Processo with the approved Pessoa–Processo links and controls. Earlier Expedição references remain historical only. This is a scope clarification, not a new architecture or implementation decision.

- Decision authority and selected stack: recorded in REI-195 and approved PRD, retrieved on 2026-11-19.
- Synthetic/production boundary and activation gates: cross-checked against PRD section 10.1 on 2026-11-19; all infrastructure gates remain open.
- No PJe integration, import, institutional data, or homologation is added by this ADR.
- Initial administrator bootstrap: cross-checked against the decision in REI-235 and the PRD on 2026-11-19; token handling and single-use account creation are separate from session issuance, MFA, and functional authorization. No secret values are recorded here.
- This record documents an existing approved decision; it does not constitute runtime, security, deployment, or institutional acceptance evidence.
