# Manual QA: Session regeneration on login

1. Open the SparkCMS login page in a private browser window.
2. Inspect the session cookie value before logging in.
3. Log in with valid credentials.
4. Confirm that the session cookie value changes immediately after logging in, confirming that `session_regenerate_id(true)` issued a fresh session ID.
