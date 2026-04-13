<?php
chdir('../');
session_start();
require_once('db/config.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Privacy Policy - <?php echo APP_NAME; ?></title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
body { background: #f5f5f5; }
.privacy-container { max-width: 900px; margin: 0 auto; padding: 20px; }
.privacy-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; border-radius: 8px 8px 0 0; text-align: center; }
.privacy-header h1 { margin: 0; font-size: 2.5rem; }
.privacy-content { background: white; padding: 40px; border-radius: 0 0 8px 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); line-height: 1.8; }
.privacy-content h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; margin-top: 30px; margin-bottom: 15px; }
.privacy-content h3 { color: #555; margin-top: 20px; }
.privacy-content ul, .privacy-content li { margin: 10px 0; }
.footer-nav { text-align: center; margin-top: 30px; }
.footer-nav a { margin: 0 15px; text-decoration: none; color: #667eea; }
</style>
</head>
<body>
<div class="privacy-container">
<div class="privacy-header">
<h1>Privacy Policy</h1>
<p>Your Data Protection Rights</p>
</div>
<div class="privacy-content">

<h2>1. Introduction</h2>
<p>This Privacy Policy explains how <strong><?php echo APP_NAME; ?></strong> (powered by Tech Hub, ofx_steve) collects, uses, stores, and protects personal data when schools use our system. We are committed to protecting your privacy and ensuring you have a positive experience on our platform.</p>

<h2>2. Information We Collect</h2>
<p>We collect the following types of information in the course of system operation:</p>
<ul>
<li><strong>Student Information:</strong> Name, admission number, class, gender, contact details, academic records, exam results</li>
<li><strong>Parent/Guardian Information:</strong> Names, phone numbers, email addresses, relationship to student</li>
<li><strong>Staff Information:</strong> Names, roles, email addresses, staff ID, department/designation</li>
<li><strong>Financial Data:</strong> Fee payments, payment methods, balances, receipts, transaction history</li>
<li><strong>System Data:</strong> Login activity, IP addresses, device information, timestamps, system usage patterns</li>
<li><strong>Communication Data:</strong> Messages sent via SMS, email, and in-app notifications</li>
</ul>

<h2>3. Legal Basis for Data Processing</h2>
<p>We process personal data under the following legal bases:</p>
<ul>
<li><strong>Performance of Contract:</strong> To provide system services to schools</li>
<li><strong>Legal Obligation:</strong> To comply with Kenya Data Protection Act, 2019</li>
<li><strong>Legitimate Interest:</strong> To improve system functionality and security</li>
<li><strong>Consent:</strong> Where explicit consent has been obtained</li>
</ul>

<h2>4. How We Use Information</h2>
<p>We use collected data to:</p>
<ul>
<li>Manage and maintain complete student, staff, and parent records</li>
<li>Track academic performance and generate report cards</li>
<li>Manage school fees, record payments, and generate financial reports</li>
<li>Send communications (SMS, email, notifications) to authorized recipients</li>
<li>Generate attendance and discipline records</li>
<li>Create certificates and official documents</li>
<li>Improve system performance, security, and user experience</li>
<li>Comply with legal and regulatory requirements</li>
<li>Audit system access and transaction history</li>
</ul>

<h2>5. Data Storage and Security</h2>
<ul>
<li><strong>Encryption:</strong> Sensitive data is encrypted in transit and at rest</li>
<li><strong>Authentication:</strong> Multi-factor authentication controls who can access data</li>
<li><strong>Access Controls:</strong> Role-based permissions restrict data access to authorized staff only</li>
<li><strong>Secure Servers:</strong> Data is stored on secure, redundant servers with regular backups</li>
<li><strong>Employee Training:</strong> All staff with data access receive data protection training</li>
<li><strong>Audit Logs:</strong> All access to sensitive data is logged and monitored</li>
</ul>

<h2>6. Data Sharing</h2>
<p><strong>We DO NOT sell personal data.</strong> Data may only be shared:</p>
<ul>
<li>With authorized school staff who require the information for their roles</li>
<li>With trusted service providers (e.g., SMS gateways, payment processors) under strict confidentiality agreements</li>
<li>When required by law or court order</li>
<li>With parents/guardians regarding their children's information</li>
<li>In anonymized or aggregated form for statistical analysis</li>
</ul>

<h2>7. Data Retention</h2>
<ul>
<li><strong>Active Use:</strong> Data is retained while the school actively uses <?php echo APP_NAME; ?></li>
<li><strong>After Termination:</strong> Schools have 90 days to export data before it is securely deleted</li>
<li><strong>Legal Hold:</strong> Data may be retained longer if required by law or for legitimate disputes</li>
<li><strong>Backup Retention:</strong> Backups may be retained for up to 1 year for disaster recovery</li>
</ul>

<h2>8. Your Rights Under Kenya Data Protection Act, 2019</h2>
<p>Individuals have the right to:</p>
<ul>
<li>Access their personal data held by the school</li>
<li>Request correction of inaccurate data</li>
<li>Request deletion of data in certain circumstances</li>
<li>Withdraw consent at any time</li>
<li>Lodge a complaint with the Data Protection Commissioner</li>
<li>Data portability (obtain data in standard format)</li>
</ul>

<h2>9. Children's Data</h2>
<p><?php echo APP_NAME; ?> handles student data under the authority and consent of the school. The school is responsible for:</p>
<ul>
<li>Obtaining parental/guardian consent where required by law</li>
<li>Ensuring children's data is protected appropriately</li>
<li>Managing parent/guardian access rights</li>
<li>Complying with education-specific data protection requirements</li>
</ul>

<h2>10. Third-Party Services</h2>
<p>We use third-party services for specific functions:</p>
<ul>
<li><strong>SMS Gateway:</strong> Safaricom and other SMS providers for notifications</li>
<li><strong>Payment Processing:</strong> M-Pesa and bank payment processors</li>
<li><strong>Email Services:</strong> Email providers for communications</li>
<li><strong>Cloud Storage:</strong> Secure cloud providers for backups</li>
</ul>
<p>All third parties are vetted and operate under data confidentiality agreements.</p>

<h2>11. International Data Transfer</h2>
<p>All data is stored within Kenya. We do not transfer data outside Kenya without explicit consent and compliance with the Data Protection Act.</p>

<h2>12. Security Incidents</h2>
<p>In the event of a data breach or security incident, we will:</p>
<ul>
<li>Notify affected parties as soon as possible</li>
<li>Cooperate fully with data protection authorities</li>
<li>Take immediate steps to contain and remediate the breach</li>
<li>Provide guidance on protective measures</li>
</ul>

<h2>13. Policy Changes</h2>
<p>We may update this Privacy Policy to reflect:</p>
<ul>
<li>Changes in data protection law</li>
<li>New features or services</li>
<li>Improved security measures</li>
</ul>
<p>Schools will be notified of material changes 30 days in advance.</p>

<h2>14. Contact Us</h2>
<p>For privacy concerns or to exercise your rights, contact:</p>
<ul>
<li><strong>Provider:</strong> Tech Hub, ofx_steve</li>
<li><strong>Email:</strong> ofxsteve.techsolutions@gmail.com</li>
<li><strong>Phone:</strong> +254717876564</li>
<li><strong>Data Protection Officer:</strong> Available upon request</li>
</ul>

<div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 0.9rem;">
<p><strong>Effective Date:</strong> 13 April 2026</p>
<p><strong>Last Updated:</strong> 13 April 2026</p>
<p>This privacy policy complies with the <strong>Kenya Data Protection Act, 2019</strong></p>
</div>

</div>

<div class="footer-nav">
<a href="index.php">← Back</a>
<a href="terms">Terms & Conditions</a>
</div>

</div>

<script src="js/jquery-3.7.0.min.js"></script>
</body>
</html>
