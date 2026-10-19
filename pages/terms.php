<?php
require_once '../logic/session_config.php';
require_once '../logic/version.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(zpgc_asset('../css/terms.css')); ?>">
    <title><?php echo htmlspecialchars(zpgc_page_title('Terms of Service')); ?></title>
</head>
<body class="terms-page">
    <div class="terms-shell">
        <div class="terms-topbar">
            <a class="terms-back" href="login_signup.php?form=signup">Back to Signup</a>
        </div>

        <article class="terms-card">
            <header class="terms-hero">
                <img class="terms-hero-logo" src="../images/ZPGC.com.png" alt="ZPGC.com">
                <h1>Terms of Service &amp; System Use Agreement</h1>
                <p class="terms-hero-lead">
                    AI-Assisted Web IT Service Desk for university students, faculty, staff,
                    technicians, and administrators.
                </p>
                <div class="terms-meta">
                    <span>Last updated: <?php echo htmlspecialchars(ZPGC_DOC_FREEZE); ?></span>
                    <span><?php echo htmlspecialchars(ZPGC_PRODUCT_LABEL); ?></span>
                </div>
            </header>

            <div class="terms-body">
                <p class="terms-intro">
                    By creating an account, verifying your email, logging in, submitting IT support
                    requests, or accessing any module of <strong>ZPGC Services</strong>, you agree to
                    comply with and be bound by these Terms of Service and Rules of Use. If you are a
                    student, faculty, or staff member, your use of this platform is also subject to the
                    university’s Acceptable Use Policy for IT resources.
                </p>

                <section class="terms-section">
                    <h2>1. Acceptance of Terms</h2>
                    <p>
                        Checking <strong>I agree to the Terms of Agreement</strong> during signup, or
                        continuing to use the system after login, means you have read, understood, and
                        accepted these terms. If you do not agree, do not create an account or use the
                        service desk.
                    </p>
                </section>

                <section class="terms-section">
                    <h2>2. User Eligibility &amp; Account Access</h2>
                    <ul>
                        <li>
                            <strong>Authorized access only.</strong> Access is restricted to active
                            students, faculty, staff, and authorized IT technicians and administrators of
                            the university.
                        </li>
                        <li>
                            <strong>Authentication.</strong> Users must register with a TSU Outlook
                            address (@student.tsu.edu.ph or @tsu.edu.ph). New accounts require email
                            verification. Students and staff activate after the code; technicians complete
                            an application for administrator approval.
                        </li>
                        <li>
                            <strong>Account responsibility.</strong> You must keep your password
                            confidential. Use Forgot Password only for your own account. Any ticket,
                            message, or rating submitted under your account is treated as originating
                            from you.
                        </li>
                    </ul>
                </section>

                <section class="terms-section">
                    <h2>3. Proper Use &amp; Code of Conduct</h2>
                    <p>When submitting tickets or communicating through the mailbox, you agree to:</p>
                    <ul>
                        <li>
                            <strong>Accurate information.</strong> Provide truthful, precise descriptions
                            of hardware, software, network, account, or related campus IT issues.
                        </li>
                        <li>
                            <strong>No abusive content.</strong> Do not post offensive, defamatory, or
                            abusive language in ticket subjects, descriptions, or messages with
                            technicians.
                        </li>
                        <li>
                            <strong>System integrity.</strong> Do not bypass access controls, probe for
                            vulnerabilities, submit spam or duplicate junk tickets, or tamper with the AI
                            ticket classification, priority scoring, or routing features.
                        </li>
                        <li>
                            <strong>Sensitive data.</strong> Do not place passwords, one-time codes,
                            payment details, or unrelated personal documents in ticket text or mailbox
                            attachments when they are not required for support.
                        </li>
                    </ul>
                </section>

                <section class="terms-section">
                    <h2>4. Role-Based Access Control (RBAC) &amp; Data Privacy</h2>
                    <ul>
                        <li>
                            <strong>Students and staff.</strong> Campus requesters sign up as Student or
                            Staff. They may view, monitor, confirm, and manage only their own tickets and
                            related mailbox threads. Both use the User workspace; the account type is
                            stored for reporting.
                        </li>
                        <li>
                            <strong>Technicians &amp; administrators.</strong> Technicians and
                            Administrators receive access limited to the functions needed for assignment,
                            resolution, user management, performance review, and system oversight.
                        </li>
                        <li>
                            <strong>Data protection.</strong> Account details, ticket history, and
                            messages are stored for campus IT support and protected under role-based
                            access. Passwords are stored as secure hashes. Ticket subject, description,
                            and AI guidance are encrypted at rest (AES-256-GCM). Administrative account and
                            ticket changes may be recorded in the Audit security check. Email
                            notifications may be sent when enabled in My Profile. See the campus privacy
                            note in the project documentation.
                        </li>
                    </ul>
                </section>

                <section class="terms-section">
                    <h2>5. Automated AI Classification &amp; Prioritization</h2>
                    <ul>
                        <li>
                            <strong>AI processing.</strong> Tickets may be processed with OpenAI language
                            models. If the paid model is unavailable, keyword rules still file and score
                            the ticket; the system retries the model on later page loads and stores the
                            OpenAI result when it succeeds. Category labels include Hardware, Software,
                            Network, Account, and Other, with priority bands Low, Moderate, and Critical
                            based on urgency and impact.
                        </li>
                        <li>
                            <strong>Routing assistance.</strong> Moderate and Critical tickets may be
                            assigned automatically to an available technician. Low-priority tickets may
                            receive troubleshooting guidance before a technician is requested.
                        </li>
                        <li>
                            <strong>Manual overrides.</strong> AI output assists efficiency. Administrators
                            and assigned technicians may reclassify, reprioritize, reassign, or otherwise
                            adjust tickets after technical assessment.
                        </li>
                    </ul>
                </section>

                <section class="terms-section">
                    <h2>6. Service Availability &amp; Performance</h2>
                    <ul>
                        <li>
                            Response and resolution times depend on priority, queue capacity, and
                            technician availability. Administrators see office-wide average response time
                            and resolution rate on Performance, with an ISO/IEC 25010:2023 quality view
                            and an on-demand concurrent health check.
                        </li>
                        <li>
                            Scheduled or emergency maintenance, local server outages, or third-party AI
                            service limits may cause temporary unavailability or delayed classification.
                        </li>
                        <li>
                            Users may be asked to confirm whether an issue is solved. After resolution,
                            optional satisfaction feedback may be collected to improve service quality.
                        </li>
                    </ul>
                </section>

                <section class="terms-section">
                    <h2>7. Account &amp; Service Modification</h2>
                    <p>
                        Administrators may activate, deactivate, edit, or restrict any account that
                        violates these guidelines, fails verification requirements, or engages in
                        unauthorized activity. The university and ZPGC Services maintainers may update
                        these terms; continued use after an update constitutes acceptance of the revised
                        terms.
                    </p>
                </section>

                <footer class="terms-footer">
                    <div class="terms-footer-actions">
                        <a class="terms-btn terms-btn-ghost" href="login_signup.php">Login</a>
                        <a class="terms-btn terms-btn-primary" href="login_signup.php?form=signup">Return to Signup</a>
                    </div>
                </footer>
            </div>
        </article>
    </div>
</body>
</html>
