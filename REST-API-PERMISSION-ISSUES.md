# WordPress REST API Permission Callback Issues

This document details all 10 instances of permission callback issues found by the WordPress Plugin Review Team.

---

## Issue #1: /page-settings/enable-all - Missing $request Parameter

### Location
**File:** `includes/API/class-rest-controller.php`
**Line:** 198-207

### Code
```php
register_rest_route(
    $this->namespace,
    '/page-settings/enable-all',
    array(
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'enable_all_pages' ),
            'permission_callback' => array( $this, 'admin_permissions_check' ),
        ),
    )
);
```

### The Problem

The permission callback `admin_permissions_check()` is defined at line 912 as:

```php
public function admin_permissions_check() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return new WP_Error(
            'rest_forbidden',
            __( 'Sorry, you are not allowed to do that.', 'surefeedback-cloud' ),
            array( 'status' => rest_authorization_required_code() )
        );
    }
    return true;
}
```

**MISTAKE:** The method signature has **zero parameters**, but WordPress REST API automatically passes the `WP_REST_Request $request` object to all permission callbacks.

### Consequences

1. **PHP 8+ Compatibility Error:** When WordPress calls this permission callback and passes the request object, PHP 8+ will throw a "too few arguments" error
2. **Broken Access Control:** The error can prevent the permission check from executing properly
3. **Runtime Failures:** May cause fatal errors or warnings depending on PHP version

### Severity
**HIGH** - Affects endpoint security and PHP 8+ compatibility

---

## Issue #2: /connection/health - Missing $request Parameter

### Location
**File:** `includes/API/class-rest-controller.php`
**Line:** 125-135

### Code
```php
register_rest_route(
    $this->namespace,
    '/connection/health',
    array(
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_connection_health' ),
            'permission_callback' => array( $this, 'admin_permissions_check' ),
        ),
    )
);
```

### The Problem

**MISTAKE:** The method `admin_permissions_check()` is declared with **no parameters**, even though WordPress REST API passes the request object to permission callbacks.

### Consequences

1. **PHP 8+ Errors:** WordPress passes `WP_REST_Request $request` to the callback, triggering runtime errors
2. **Failed Permission Checks:** The mismatch can cause the permission check to fail or not execute
3. **Security Risk:** Endpoint protection may be compromised

### Severity
**HIGH** - Affects endpoint security and PHP 8+ compatibility

---

## Issue #3: /webhook - Improper Permission Architecture

### Location
**File:** `includes/API/class-webhook-controller.php`
**Line:** 51-61

### Code
```php
register_rest_route(
    $this->namespace,
    '/webhook',
    array(
        array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'handle_webhook' ),
            'permission_callback' => '__return_true',
        ),
    )
);
```

### The Problem

**MISTAKE #1:** The endpoint uses `permission_callback => '__return_true'`, making it **publicly accessible without any REST-level authentication**.

**MISTAKE #2:** The endpoint is **CREATABLE** (POST method) and performs sensitive operations:
- Stores bearer tokens (line 163)
- Updates critical connection options (site_id, organization_id, script_token, etc.)
- Establishes connection authentication

**MISTAKE #3:** Security validation is performed **inside the callback** (lines 106-147) rather than in the `permission_callback`:
```php
public function handle_webhook( $request ) {
    // State validation happens HERE (inside callback)
    $state        = $body['state'] ?? '';
    $state_header = $request->get_header( 'X-SureFeedback-State' );
    $provided_state = ! empty( $state ) ? $state : $state_header;

    if ( empty( $provided_state ) ) {
        return new WP_Error(...); // Security check is too late!
    }
    // More validation...
}
```

### Consequences

1. **Bypasses WordPress REST Security Architecture:** WordPress expects permission checks in `permission_callback`, not inside the handler
2. **Endpoint is Listed as Public:** Anyone can see this endpoint is publicly accessible via REST API discovery
3. **Potential Security Scanners Flag:** Security tools may flag this as a vulnerability
4. **Not Following WordPress Standards:** Violates WordPress REST API best practices

### Severity
**CRITICAL** - Improper security architecture, handles sensitive authentication data

---

## Issue #4: /verification/verify - Missing $request Parameter

### Location
**File:** `includes/API/class-rest-controller.php`
**Line:** 227-237

### Code
```php
register_rest_route(
    $this->namespace,
    '/verification/verify',
    array(
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'verify_integration' ),
            'permission_callback' => array( $this, 'admin_permissions_check' ),
        ),
    )
);
```

### The Problem

**MISTAKE:** The method uses `admin_permissions_check()` which is declared with **no $request parameter**, even though WP REST passes the request object.

### Consequences

1. **Too-Many-Arguments Errors:** Especially on PHP 8+, WordPress passing the request object to a zero-parameter method triggers errors
2. **Broken Protection:** The error can prevent the permission check from running
3. **Verification Endpoint Vulnerable:** This endpoint verifies integration with Laravel API

### Severity
**HIGH** - Affects endpoint security and PHP 8+ compatibility

---

## Issue #5: /settings/general (GET) - Missing $request Parameter

### Location
**File:** `includes/API/class-rest-controller.php`
**Line:** 159-167 (first array element)

### Code
```php
register_rest_route(
    $this->namespace,
    '/settings/general',
    array(
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_general_settings' ),
            'permission_callback' => array( $this, 'admin_permissions_check' ),
        ),
        // ... POST method below
    )
);
```

### The Problem

**MISTAKE:** The permission callback has an **incompatible signature** (no $request param) for WP REST permission callbacks.

### Consequences

1. **Runtime Errors:** WordPress REST passes the request object, but the method accepts zero parameters
2. **PHP 8+ Issues:** Strict typing and parameter validation will cause this to fail
3. **Authorization May Fail:** The permission check might not execute properly
4. **Settings Exposure Risk:** General settings (user roles, etc.) could be exposed

### Severity
**HIGH** - Affects endpoint security and PHP 8+ compatibility

---

## Issue #6: /settings/general (POST) - Missing $request Parameter

### Location
**File:** `includes/API/class-rest-controller.php`
**Line:** 168-173 (second array element)

### Code
```php
register_rest_route(
    $this->namespace,
    '/settings/general',
    array(
        // ... GET method above
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'update_general_settings' ),
            'permission_callback' => array( $this, 'admin_permissions_check' ),
        ),
    )
);
```

### The Problem

**MISTAKE:** The method signature lacks the `$request` parameter that WordPress REST API provides to permission callbacks.

### Consequences

1. **Signature Mismatch Errors:** WordPress passes `WP_REST_Request $request`, but the method takes zero parameters
2. **PHP 8+ Failure:** Modern PHP versions will error on argument count mismatch
3. **Failed Authorization:** The permission callback may not execute
4. **Data Modification Risk:** This is a CREATABLE (POST) endpoint that updates `surefeedback_allowed_roles` option

### Severity
**HIGH** - Affects endpoint security and PHP 8+ compatibility, protects data modification

---

## Issue #7: /page-settings (GET) - Missing $request Parameter

### Location
**File:** `includes/API/class-rest-controller.php`
**Line:** 181-189 (first array element)

### Code
```php
register_rest_route(
    $this->namespace,
    '/page-settings',
    array(
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_page_settings' ),
            'permission_callback' => array( $this, 'admin_permissions_check' ),
        ),
        // ... POST method below
    )
);
```

### The Problem

**MISTAKE:** The method is defined without accepting the `$request` parameter that WordPress REST automatically passes.

### Consequences

1. **Incompatible Signature:** WordPress calls permission callbacks with the request object, but this method has zero parameters
2. **Runtime Failures:** Especially on PHP 8+, argument count mismatches cause errors
3. **Broken Access Control:** The permission check might fail to execute
4. **Compliance Violation:** Doesn't follow WordPress REST API standards

### Severity
**HIGH** - Affects endpoint security and PHP 8+ compatibility

---

## Issue #8: /page-settings (POST) - Missing $request Parameter

### Location
**File:** `includes/API/class-rest-controller.php`
**Line:** 190-195 (second array element)

### Code
```php
register_rest_route(
    $this->namespace,
    '/page-settings',
    array(
        // ... GET method above
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'update_page_settings' ),
            'permission_callback' => array( $this, 'admin_permissions_check' ),
        ),
    )
);
```

### The Problem

**MISTAKE:** The method is defined with **no parameters**, even though WordPress REST always passes the request object to permission callbacks.

### Consequences

1. **Argument Mismatch:** WordPress invokes the callback with `$request`, but the method signature accepts nothing
2. **PHP 8+ Errors:** Modern PHP enforces stricter parameter checking
3. **Undermined Protection:** The permission check may error out
4. **Data Integrity Risk:** This CREATABLE endpoint updates `surefeedback_page_settings`

### Severity
**HIGH** - Affects endpoint security and PHP 8+ compatibility, protects configuration data

---

## Issue #9: /webhook/disconnect - Improper Permission Architecture

### Location
**File:** `includes/API/class-webhook-controller.php`
**Line:** 63-73

### Code
```php
register_rest_route(
    $this->namespace,
    '/webhook/disconnect',
    array(
        array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'handle_disconnect_webhook' ),
            'permission_callback' => '__return_true',
        ),
    )
);
```

### The Problem

**MISTAKE #1:** The endpoint uses `permission_callback => '__return_true'`, making it **publicly accessible** at the REST API permission layer.

**MISTAKE #2:** The endpoint is **CREATABLE** (POST method) and performs destructive operations:
- Deletes secure cookie for auth_token
- Deletes bearer_token, connection_id, site_id, organization_id
- Deletes access_token, site_token, parent_url, widget_script_url
- Removes all connection and verification data

**MISTAKE #3:** The security validation in the handler (lines 234-245) is **conditionally applied** and has a critical flaw:

```php
public function handle_disconnect_webhook( $request ) {
    $webhook_secret    = $request->get_header( 'X-Webhook-Secret' );
    $stored_site_token = get_option( 'surefeedback_site_token', '' );

    // ONLY validates if BOTH values are present
    if ( ! empty( $webhook_secret ) && ! empty( $stored_site_token ) ) {
        if ( $webhook_secret !== $stored_site_token ) {
            return new WP_Error(...);
        }
    }
    // If header is missing, validation is SKIPPED!
    // Proceeds to delete all connection data...
}
```

**CRITICAL FLAW:** If the `X-Webhook-Secret` header is **not provided** or if `surefeedback_site_token` is empty, the entire validation is bypassed and the endpoint proceeds to delete all connection data.

### Consequences

1. **Unauthenticated Disconnect:** Anyone can disconnect the site by calling this endpoint without any credentials
2. **Bypass via Missing Header:** Not sending the `X-Webhook-Secret` header bypasses all security checks
3. **Destructive Operation:** All connection data is permanently deleted without proper authorization
4. **Not Following WordPress Standards:** Security should be in `permission_callback`, not inside the handler
5. **Critical Security Vulnerability:** This is a public endpoint that can wipe out all plugin configuration

### Severity
**CRITICAL** - Public endpoint with destructive operations, missing header bypasses all security

---

## Issue #10: /connection/disconnect - Missing $request Parameter

### Location
**File:** `includes/API/class-rest-controller.php`
**Line:** 82-92

### Code
```php
register_rest_route(
    $this->namespace,
    '/connection/disconnect',
    array(
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'disconnect_from_saas' ),
            'permission_callback' => array( $this, 'admin_permissions_check' ),
        ),
    )
);
```

### The Problem

**MISTAKE:** The permission callback uses `admin_permissions_check()` but the method signature **lacks the $request parameter** expected by WordPress REST.

### Consequences

1. **Parameter Count Mismatch:** WordPress REST calls permission callbacks with the request object, but this method accepts zero arguments
2. **PHP 8+ Runtime Errors:** Strict parameter enforcement causes too-many-arguments errors
3. **Failed Authorization Check:** The error may prevent proper execution
4. **Bypass Risk:** This CREATABLE endpoint disconnects the site from SaaS and deletes critical data

### Severity
**HIGH** - Affects endpoint security and PHP 8+ compatibility, protects destructive disconnect operation

---

## Summary

### Root Causes

1. **admin_permissions_check() Method Signature** (8 instances)
   - Located at line 912 in `includes/API/class-rest-controller.php`
   - Defined as: `public function admin_permissions_check()`
   - Should be: `public function admin_permissions_check( $request )`

2. **Webhook Endpoints with __return_true** (2 instances)
   - Located in `includes/API/class-webhook-controller.php`
   - Security checks performed inside callbacks instead of permission_callback
   - Issue #9 has critical bypass vulnerability (missing header skips validation)

### Severity Breakdown

- **CRITICAL (2):** Issues #3 and #9 (webhook endpoints)
- **HIGH (8):** Issues #1, #2, #4, #5, #6, #7, #8, #10 (admin_permissions_check)

### Most Critical Issue

**Issue #9 (/webhook/disconnect)** is the most severe - it's a public endpoint that can delete all connection data if the `X-Webhook-Secret` header is simply not provided.

### Fix Required

1. Update `admin_permissions_check()` method signature to accept `$request` parameter
2. Move webhook security validation from inside callbacks to proper `permission_callback` methods
3. Ensure webhook disconnect endpoint ALWAYS requires authentication (no conditional checks)
