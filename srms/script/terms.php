<?php
chdir('../');
session_start();
require_once('db/config.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Terms & Conditions - <?php echo APP_NAME; ?></title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
body { background: #f5f5f5; }
.terms-container { max-width: 900px; margin: 0 auto; padding: 20px; }
.terms-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; border-radius: 8px 8px 0 0; text-align: center; }
.terms-header h1 { margin: 0; font-size: 2.5rem; }
.terms-content { background: white; padding: 40px; border-radius: 0 0 8px 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); line-height: 1.8; }
.terms-content h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; margin-top: 30px; margin-bottom: 15px; }
.terms-content h3 { color: #555; margin-top: 20px; }
.terms-content ul, .terms-content li { margin: 10px 0; }
.footer-nav { text-align: center; margin-top: 30px; }
.footer-nav a { margin: 0 15px; text-decoration: none; color: #667eea; }
.warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
</style>
</head>
<body>
<div class="terms-container">
<div class="terms-header">
<h1>Terms & Conditions</h1>
<p>User Agreement & Service Terms</p>
</div>
<div class="terms-content">

<h2>1. Acceptance of Terms</h2>
<p>By accessing and using <strong><?php echo APP_NAME; ?></strong>, the school administrator and all authorized users agree to be bound by these Terms & Conditions, our Privacy Policy, and all applicable laws of Kenya.</p>

<h2>2. License Grant</h2>
<p><strong><?php echo APP_NAME; ?> is licensed, not sold.</strong> We grant the school a non-exclusive, non-transferable license to use the system for internal educational operations only.</p>
<ul>
<li>The school may use the system for managing students, staff, academics, and finances</li>
<li>The license is limited to the school's staffand students during the subscription period</li>
<li>Unauthorized copying, reproduction, or reverse engineering is prohibited</li>
<li>Sharing or reselling access licenses is strictly forbidden</li>
<li>The school must not sublicense or commercially exploit the system</li>
</ul>

<h2>3. Permitted Use</h2>
<p>The school agrees to use <?php echo APP_NAME; ?> for:</p>
<ul>
<li>Managing student admissions, records, and academic progress</li>
<li>Recording staff and teacher information</li>
<li>Tracking attendance and discipline</li>
<li>Managing exams, marks entry, and report cards</li>
<li>Recording and tracking school fees and payments</li>
<li>Internal communications with parents and staff</li>
<li>Generating official school documents</li>
<li>Administrative and analytical purposes</li>
</ul>

<h2>4. Prohibited Activities</h2>
<p>The school agrees NOT to:</p>
<ul>
<li>Share login credentials with unauthorized persons</li>
<li>Access the system for fraudulent, illegal, or harmful purposes</li>
<li>Attempt to hack, breach, or penetrate system security</li>
<li>Use the system to harass, threaten, or abuse individuals</li>
<li>Share student or staff data with third parties without consent</li>
<li>Use the system for commercial resale or profit</li>
<li>Interfere with system operations or availability</li>
<li>Create automated tools or bots to extract or misuse data</li>
<li>Violate any law or regulation of Kenya</li>
</ul>

<h2>5. User Responsibilities</h2>
<p>The school is responsible for:</p>
<ul>
<li>Providing accurate, complete, and current information</li>
<li>Maintaining the confidentiality of admin credentials</li>
<li>Preventing unauthorized system access</li>
<li>Ensuring staff use the system for authorized purposes only</li>
<li>Implementing internal policies on data handling</li>
<li>Regular backups of critical data</li>
<li>Reporting security incidents or suspicious activity immediately</li>
<li>Complying with education and data protection laws</li>
</ul>

<h2>6. Subscription & Fees</h2>
<ul>
<li>Schools must pay agreed subscription or setup fees on the schedule specified in the contract</li>
<li>Failure to pay within 30 days of invoice may result in service suspension</li>
<li>Payment terms and pricing are specified in the separate Service Agreement</li>
<li>Refunds are subject to the refund policy outlined in the Service Agreement</li>
<li>Price increases will be communicated 60 days in advance</li>
</ul>

<h2>7. System Availability & Maintenance</h2>
<p>We strive to maintain high system availability, but we do not guarantee uninterrupted service:</p>
<ul>
<li>Scheduled maintenance windows will be announced 7 days in advance when possible</li>
<li>Unscheduled downtime due to emergencies may occur</li>
<li>Emergency patches will be deployed immediately without notice</li>
<li>We are not liable for data loss or operational interruptions due to technical issues</li>
<li>We maintain automated backups; however, the school should maintain its own backups</li>
</ul>

<h2>8. Data Ownership & Responsibility</h2>
<ul>
<li><strong>The school owns all data</strong> entered into <?php echo APP_NAME; ?></li>
<li><strong>The system provider acts as a data processor</strong>, not the data controller</li>
<li>The school is responsible for data accuracy, completeness, and legality</li>
<li>The school must ensure proper consent for collecting student and parent data</li>
<li>The school must comply with Kenya's Data Protection Act, 2019</li>
<li>We do not verify data accuracy; the school must ensure data integrity</li>
</ul>

<h2>9. Limitation of Liability</h2>
<p><strong>The system provider is not liable for:</strong></p>
<ul>
<li>Data loss due to misuse, negligence, or backup failures</li>
<li>Financial or operational losses resulting from service downtime</li>
<li>Errors resulting from incorrect or incomplete data entry</li>
<li>Breach of data by third parties or unauthorized access due to weak passwords</li>
<li>Indirect, incidental, or consequential damages</li>
<li>Expired passwords, lost credentials, or locked accounts</li>
</ul>

<div class="warning-box">
<strong>⚠️ Important:</strong> The school assumes all responsibility for maintaining data backups and security. The system provider is not a substitute for proper data management and disaster recovery practices.
</div>

<h2>10. Account Termination</h2>
<p>We may suspend or terminate the school's access if:</p>
<ul>
<li>Terms & Conditions are materially violated</li>
<li>Fees are not paid after 60 days past due</li>
<li>The school uses the system illegally or fraudulently</li>
<li>The school violates data protection or education laws</li>
<li>Repeated security violations or hacking attempts occur</li>
</ul>
<p><strong>Schools may terminate use at any time</strong> upon written notice. Termination does not release the school from payment obligations.</p>

<h2>11. Data Post-Termination</h2>
<ul>
<li>Upon termination, the school will have <strong>90 days</strong> to export all data</li>
<li>After 90 days, we reserve the right to securely delete all school data</li>
<li>We are not responsible for data recovery after the 90-day period</li>
<li>The school should maintain its own archive copies</li>
</ul>

<h2>12. System Updates & Modifications</h2>
<p>We may update, modify, or discontinue features:</p>
<ul>
<li>Major feature changes will be communicated 60 days in advance</li>
<li>Bug fixes and security patches may be deployed immediately</li>
<li>Continued use of the system implies acceptance of updates</li>
<li>We will not deliberately remove core functionality without alternatives</li>
</ul>

<h2>13. Intellectual Property</h2>
<ul>
<li><?php echo APP_NAME; ?> and its code are the intellectual property of the system provider</li>
<li>All trademarks, logos, and design elements are protected</li>
<li>Schools may not modify, reverse-engineer, or extract code</li>
<li>Custom developments remain the property of the system provider unless otherwise agreed</li>
<li>Schools own their own content and data</li>
</ul>

<h2>14. Support & Service Level</h2>
<ul>
<li>Basic support is included during business hours (9 AM - 5 PM, Monday - Friday)</li>
<li>Emergency support for critical issues is available</li>
<li>Response times depend on issue severity</li>
<li>Premium support may be available for an additional fee</li>
</ul>

<h2>15. Changes to Terms</h2>
<p>We may update these Terms & Conditions at any time. Material changes will be communicated 30 days in advance. Continued use after notice constitutes acceptance.</p>

<h2>16. Governing Law & Dispute Resolution</h2>
<ul>
<li>These Terms are governed by the laws of Kenya</li>
<li>All disputes shall be resolved through negotiation or arbitration under Kenya law</li>
<li>Data protection disputes also fall under the <strong>Kenya Data Protection Act, 2019</strong></li>
<li>The school agrees to the jurisdiction of Kenyan courts</li>
</ul>

<h2>17. Compliance with Education Laws</h2>
<p>Schools agree to use <?php echo APP_NAME; ?> in compliance with:</p>
<ul>
<li>The Basic Education Act, 2013</li>
<li>The Kenya Data Protection Act, 2019</li>
<li>The Children Act, 2001</li>
<li>All Ministry of Education guidelines and regulations</li>
<li>County education policies</li>
</ul>

<h2>18. Third-Party Integrations</h2>
<ul>
<li><?php echo APP_NAME; ?> may integrate with third-party services (M-Pesa, SMS gateways, etc.)</li>
<li>Those services are governed by their own terms</li>
<li>We are not responsible for third-party service failures</li>
<li>The school accepts risks associated with third-party integrations</li>
</ul>

<h2>19. Contact Information</h2>
<ul>
<li><strong>Provider:</strong> School System Provider</li>
<li><strong>Email:</strong> ofxsteve.techsolutions@gmail.com</li>
<li><strong>Phone:</strong> +254717876564</li>
<li><strong>For disputes:</strong> Legal notices should be sent via registered mail</li>
</ul>

<div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 0.9rem;">
<p><strong>Effective Date:</strong> 13 April 2026</p>
<p><strong>Last Updated:</strong> 13 April 2026</p>
<p>By using <?php echo APP_NAME; ?>, you acknowledge that you have read and agree to these Terms & Conditions.</p>
</div>

</div>

<div class="footer-nav">
<a href="index.php">← Back</a>
<a href="privacy">Privacy Policy</a>
</div>

</div>

<script src="js/jquery-3.7.0.min.js"></script>
</body>
</html>
