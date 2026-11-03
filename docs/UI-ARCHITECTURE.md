# MCC UI Architecture

The MCC application uses a server-rendered CodeIgniter 4 presentation layer. Controllers and services continue to provide data and enforce authorization; views render presentation only.

## Shared shell

Authenticated screens include `app/Views/staff_header.php` and `staff_footer.php`. The shell composes the reusable sidebar, topbar, breadcrumbs, alerts, and footer from `app/Views/partials/`. Navigation is supplied by the existing authorization-aware controllers. The view never grants access.

Authentication uses `app/Views/login.php` with the same design tokens and a simplified auth layout.

## Components and styles

Reusable presentation components live in `app/Views/components/` for badges, cards, empty states, and form fields. Shared styles are centralized under `public/assets/css/`:

- `app.css` contains design tokens, shell layout, responsive behavior, and compatibility styles for current screens.
- `components.css` contains reusable component states.
- `utilities.css` contains small layout/accessibility utilities.
- `public/assets/js/app.js` provides only shell behavior: responsive sidebar toggle, backdrop close, and Escape-key close.

The visual language uses the supplied Valex reference's dark compact sidebar, light header, lavender primary accent, restrained borders/shadows, compact system typography, grouped navigation, responsive grids, and scrollable data tables. Icons use the project-owned self-hosted Font Awesome asset set; no proprietary Valex source, asset, icon, or font is used.

The product navigation architecture is Admissions & CRM, Players, Coaching, Attendance, Matches & Tournaments, Grounds & Equipment, High Performance, Reports, Communication, and Administration. The sidebar renders only existing authorization-provided routes. Equipment inventory is linked under Grounds & Equipment for the limited create/list/edit/soft-delete scope in `EQUIP-01`; its wider workflows remain deferred. Programs and Batches are linked under Coaching for the limited user-directed create/list scope in `PROG-BATCH-01`; lifecycle and dependent workflows remain deferred. The shared topbar contains an active-branch selector for a Branch Admin with multiple currently assigned active branches. The selector posts through an authenticated CSRF-protected route, and the server resolves branch authority from current database assignments. Existing modules continue to apply their existing branch filters and do not consume this active branch context.

## Presentation rules

Views must not query the database, contain SQL, calculate business KPIs, or make authorization decisions. New module screens should consume the shared shell and component styles instead of adding module-specific visual systems. The persistent `mcc_cricket_club_test` database is not used by this UI work, and destructive database tests remain forbidden.
## Authoritative consistency rule

All current and future screens must follow the shared application design system and the established Lead List, Lead Details, and User Details patterns. Reuse shared layouts, partials, components, CSS, utilities, date picker, responsive table, filter, action, badge, alert, empty-state, and overlay patterns before creating anything new. New UI must use the shared alignment, spacing, typography, token, accessibility, responsive, focus, and overflow rules. Presentation changes must not alter RBAC, validation, CSRF, authorization, routes, services, business logic, or database behavior.
