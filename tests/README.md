# Foundation test map

These tests cover generic technical invariants only. They intentionally do not assume MCC business roles, branch ownership rules, business statuses, document categories, retention periods, calculations, or portal policy.

| Concern | Current pattern | Future module requirement |
|---|---|---|
| Authentication/session | `AuthorizationServiceTest` covers unauthenticated, disabled, and invalid-context denial. | Add framework feature tests for login, rotation, logout, reset, and disablement when database identity is approved. |
| Authorization | `AuthorizationServiceTest` covers missing permission and deny-by-default behavior. | Add endpoint policy tests for every approved permission. |
| Branch isolation | `AuthorizationServiceTest` covers wrong-branch denial with explicit assignment. | Add repository/service tests using approved branch ownership rules. |
| Audit | `AuditLoggerTest` covers required metadata and redaction. | Add durable append-only store and module-event tests after audit scope is approved. |
| Privacy/logging | `SensitiveDataRedactorTest` covers nested secret and sensitive-field redaction. | Add production log sink inspection and error-response tests. |
| Private files | `PrivateStorageTest` covers private storage, traversal rejection, and no public asset path. | Add request-time file authorization tests after document policy is approved. |
| Validation | Not applicable to the foundation-only classes yet. | Add server-side validation tests with approved field requirements. |
| Concurrency | No business persistence exists yet. | Add transaction/optimistic-concurrency tests with approved schema and workflow rules. |
| API failure paths | No API endpoints exist. | Add authentication, authorization, retry, and safe-failure tests when an API consumer is approved. |
