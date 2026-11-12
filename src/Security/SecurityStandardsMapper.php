<?php

declare(strict_types=1);
namespace Lgu\GovSuite\Security;

/**
 * Compliance Standards Mapping Matrix.
 *
 * Decouples concrete security invariant rules from transient external compliance
 * numbering schemes (e.g., OWASP 2021 vs. 2025 vs. future revisions, DICT Cyber Directives,
 * and NPC Data Privacy Act Circulars).
 *
 * MAINTENANCE INSTRUCTIONS FOR AI AGENTS & DEVELOPERS:
 * When updating this package for future OWASP Top 10 revisions (e.g., OWASP 2028 or 2029):
 * 1. Change DEFAULT_OWASP_VERSION to the new edition string (e.g., '2029').
 * 2. Update ACTIVE_OWASP_LABEL (e.g., 'OWASP Top 10:2029 (Active Version)').
 * 3. In getFrameworks(), append a new framework definition mapping new categories (A01:2029..A10:2029)
 *    to existing Invariant Rule IDs (SEC-MASS-ASSIGNMENT, SEC-ENV-DEBUG-CSP, SEC-BLADE-XSS, etc.).
 * 4. Move the superseded edition (e.g., '2025') to historical benchmark status.
 * 5. Scanner code in LguAuditCommand.php requires zero edits unless a completely novel attack vector emerges.
 * 6. Consult AGENTS.md and CONTRIBUTING.md for the complete 5-step maintenance checklist.
 *
 * @author Ricky Bulalakaw
 */
class SecurityStandardsMapper
{
    public const DEFAULT_OWASP_VERSION = '2025';

    /**
     * Human-readable label for the currently enforced OWASP Top 10 version.
     */
    public const ACTIVE_OWASP_LABEL = 'OWASP Top 10:2025 (Active Version)';

    /**
     * Return registered compliance framework definitions.
     */
    public static function getFrameworks(): array
    {
        return [
            '2025' => [
                'name' => 'OWASP Top 10',
                'version' => '2025 (Active Version)',
                'tag' => 'OWASP Top 10:2025',
                'description' => 'OWASP Top 10:2025 Application Security Risks standard baseline.',
                'mappings' => [
                    'A01:2025' => [
                        'category' => 'A01:2025 - Broken Access Control',
                        'rule_id' => 'SEC-MASS-ASSIGNMENT',
                        'check' => 'Mass Assignment & Route Parameter Integrity',
                    ],
                    'A02:2025' => [
                        'category' => 'A02:2025 - Security Misconfiguration',
                        'rule_id' => 'SEC-ENV-DEBUG-CSP',
                        'check' => 'Debug Exposure & Content Security Policy',
                    ],
                    'A03:2025' => [
                        'category' => 'A03:2025 - Software Supply Chain',
                        'rule_id' => 'SEC-SUPPLY-CHAIN',
                        'check' => 'Dependency Lock & Vulnerability Audit',
                    ],
                    'A04:2025' => [
                        'category' => 'A04:2025 - Cryptographic Failures',
                        'rule_id' => 'SEC-CRYPTO-PII',
                        'check' => 'PII Encryption & Isolated Keying',
                    ],
                    'A05:2025' => [
                        'category' => 'A05:2025 - Injection',
                        'rule_id' => 'SEC-BLADE-XSS',
                        'check' => 'Blade Output Sanitization & Injection Guard',
                    ],
                    'A06:2025' => [
                        'category' => 'A06:2025 - Insecure Design',
                        'rule_id' => 'SEC-SOD-POLICY',
                        'check' => 'Institutional Segregation of Duties & Approval Ceilings',
                    ],
                    'A07:2025' => [
                        'category' => 'A07:2025 - Authentication Failures',
                        'rule_id' => 'SEC-AUTH-THROTTLE',
                        'check' => 'Login Throttling & Session Lifecycle',
                    ],
                    'A08:2025' => [
                        'category' => 'A08:2025 - Software & Data Integrity',
                        'rule_id' => 'SEC-SERIALIZATION',
                        'check' => 'Serialization Safety & Hash Chaining',
                    ],
                    'A09:2025' => [
                        'category' => 'A09:2025 - Security Logging & Alerting',
                        'rule_id' => 'SEC-AUDIT-LOGGING',
                        'check' => 'Immutable Audit Ledger Logging',
                    ],
                    'A10:2025' => [
                        'category' => 'A10:2025 - Mishandling Exceptions',
                        'rule_id' => 'SEC-FAIL-CLOSED-SCOPE',
                        'check' => 'Fail-Closed Scope & Boundary Safety',
                    ],
                ],
            ],
            '2021' => [
                'name' => 'OWASP Top 10',
                'version' => '2021 (Historical Benchmark)',
                'tag' => 'OWASP Top 10:2021',
                'description' => 'OWASP Top 10:2021 Application Security Risks baseline.',
                'mappings' => [
                    'A01:2021' => [
                        'category' => 'A01:2021 - Broken Access Control',
                        'rule_id' => 'SEC-MASS-ASSIGNMENT',
                        'check' => 'Mass Assignment & Route Parameter Integrity',
                    ],
                    'A02:2021' => [
                        'category' => 'A02:2021 - Cryptographic Failures',
                        'rule_id' => 'SEC-CRYPTO-PII',
                        'check' => 'PII Encryption & Isolated Keying',
                    ],
                    'A03:2021' => [
                        'category' => 'A03:2021 - Injection',
                        'rule_id' => 'SEC-BLADE-XSS',
                        'check' => 'Blade Output Sanitization & Injection Guard',
                    ],
                    'A04:2021' => [
                        'category' => 'A04:2021 - Insecure Design',
                        'rule_id' => 'SEC-SOD-POLICY',
                        'check' => 'Institutional Segregation of Duties & Approval Ceilings',
                    ],
                    'A05:2021' => [
                        'category' => 'A05:2021 - Security Misconfiguration',
                        'rule_id' => 'SEC-ENV-DEBUG-CSP',
                        'check' => 'Debug Exposure & Content Security Policy',
                    ],
                    'A06:2021' => [
                        'category' => 'A06:2021 - Vulnerable and Outdated Components',
                        'rule_id' => 'SEC-SUPPLY-CHAIN',
                        'check' => 'Dependency Lock & Vulnerability Audit',
                    ],
                    'A07:2021' => [
                        'category' => 'A07:2021 - Identification and Authentication Failures',
                        'rule_id' => 'SEC-AUTH-THROTTLE',
                        'check' => 'Login Throttling & Session Lifecycle',
                    ],
                    'A08:2021' => [
                        'category' => 'A08:2021 - Software and Data Integrity Failures',
                        'rule_id' => 'SEC-SERIALIZATION',
                        'check' => 'Serialization Safety & Hash Chaining',
                    ],
                    'A09:2021' => [
                        'category' => 'A09:2021 - Security Logging and Monitoring Failures',
                        'rule_id' => 'SEC-AUDIT-LOGGING',
                        'check' => 'Immutable Audit Ledger Logging',
                    ],
                    'A10:2021' => [
                        'category' => 'A10:2021 - Server-Side Request Forgery (SSRF)',
                        'rule_id' => 'SEC-FAIL-CLOSED-SCOPE',
                        'check' => 'Fail-Closed Scope & Boundary Safety',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get framework definition by version string (defaults to 2025).
     */
    public static function getFramework(string $version = self::DEFAULT_OWASP_VERSION): array
    {
        $frameworks = self::getFrameworks();

        return $frameworks[$version] ?? $frameworks[self::DEFAULT_OWASP_VERSION];
    }

    /**
     * Return Philippine Statutory vs. OWASP Reconciliation Directives.
     */
    public static function getStatutoryReconciliations(string $version = self::DEFAULT_OWASP_VERSION): array
    {
        return [
            [
                'conflict' => 'DPA & OWASP Right-to-Erasure vs. COA & NAP Immutable Retention',
                'statutory_rule' => 'COA Circulars and National Archives (NAP) mandate 10-year immutable audit and transaction retention; SQL deletion/truncation is strictly illegal.',
                'owasp_rule' => 'A01/A04 data minimization and right-to-erasure for citizen personal records.',
                'reconciliation' => 'Never execute SQL DELETE on financial, voucher, or audit tables. Implement an anonymization pipeline that masks citizen PII (name, phone, TIN, address) while preserving transaction totals, timestamps, and SHA-256 hash chains.',
            ],
            [
                'conflict' => 'OWASP Principle of Least Privilege vs. Statutory Oversight Access',
                'statutory_rule' => 'Municipal oversight offices (Accounting/MACCO, Treasury/MTO, Mayor\'s Office/MO, Internal Audit/IAS) have legal supervisory review authority over departmental disbursements.',
                'owasp_rule' => 'A01 Broken Access Control strictly segregates departmental multi-tenant data.',
                'reconciliation' => 'Do not grant wildcard database privileges to oversight staff. Implement oversight inspection via BaseGovernmentPolicy::isWithinOfficeScope() checking authorized oversight office codes, and log every cross-office read as an immutable audit activity.',
            ],
            [
                'conflict' => 'OWASP Fail-Safe Exception Handling vs. ARTA Service Continuity',
                'statutory_rule' => 'ARTA RA 11032 enforces strict 3-7-20 working day SLA clocks; service brownouts cannot silently forfeit citizen queue standings.',
                'owasp_rule' => 'A10 Mishandling of Exceptional Conditions requires strict fail-closed operations during system failure.',
                'reconciliation' => 'In municipal network interruptions, fail closed on backend database mutations while using encrypted client-side local caching (Alpine.js offline interceptor) so citizens do not lose entered draft forms.',
            ],
        ];
    }

    /**
     * Return Infrastructure Hardening Playbook for CloudPanel and Cloudflare.
     */
    public static function getInfrastructurePlaybook(): array
    {
        return [
            'cloudpanel' => [
                'title' => 'CloudPanel Hosting Blueprint (Ubuntu 24.04 / Nginx / PHP 8.4)',
                'steps' => [
                    'Nginx Security Headers: In CloudPanel Site Settings > Vhost, inject standard security headers: add_header X-Frame-Options "SAMEORIGIN" always; add_header X-Content-Type-Options "nosniff" always; add_header Referrer-Policy "strict-origin-when-cross-origin" always; add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;',
                    'PHP-FPM Hardening: In CloudPanel PHP Settings, ensure display_errors = Off, expose_php = Off, and allow_url_fopen = Off. Verify PHP process runs under site-isolated unprivileged user.',
                    'Sensitive File Blocking: Verify Nginx blocks direct HTTP access to hidden files and metadata: location ~ /\.(env|git|htaccess) { deny all; return 404; } location ~ /(composer\.(json|lock)|package(-lock)?\.json) { deny all; return 404; }',
                    'Database Trigger Freeze: Log into CloudPanel MySQL / PostgreSQL database and execute database/sql/freeze_audit_logs_trigger.sql to seal audit_activities table.',
                ],
            ],
            'cloudflare' => [
                'title' => 'Cloudflare Free Tier Blueprint (Edge Security & WAF)',
                'steps' => [
                    'SSL/TLS Encryption Mode: Navigate to SSL/TLS > Overview > Set encryption mode to "Full (Strict)". Install Cloudflare Origin CA certificate on CloudPanel or use Let\'s Encrypt with auto-renewal.',
                    'Edge HTTPS Enforcement: Under SSL/TLS > Edge Certificates, toggle ON "Always Use HTTPS" and "Automatic HTTPS Rewrites". Set Minimum TLS Version to "TLS 1.2" (or TLS 1.3).',
                    'HSTS Security Header: Under SSL/TLS > Edge Certificates > HTTP Strict Transport Security (HSTS), Enable HSTS with max-age=31536000 (1 year), includeSubDomains, and preload.',
                    'Bot & Browser Protection: Under Security > Bots, enable "Bot Fight Mode". Under Security > Settings, set Security Level to "Medium" and enable "Browser Integrity Check".',
                    'WAF Rate Limiting Rule: Under Security > WAF > Rate Limiting Rules (Free Tier provides 5 rules), create rule for URI Path equals "/login" and "/office/switch": Rate: 5 requests per 10 seconds per IP, Action: Block (1 minute).',
                ],
            ],
        ];
    }
}
