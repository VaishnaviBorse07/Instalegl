/**
 * Instalegl — Configuration
 * OTP is handled server-side via Brevo (backend/helpers.php).
 * Payment is collected via QR code screenshot upload.
 */

// ── Site Config ──────────────────────────────────────────────
var IL_SITE_NAME     = "Instalegl";
var IL_SUPPORT_EMAIL = "support@instalegl.com";
var IL_SUPPORT_PHONE = "+1 (551) 201-5873";

// ── Payment QR Config ─────────────────────────────────────────
// UPI ID shown below the QR code for manual payments
var IL_UPI_ID        = "instalegl@upi";
// Static QR code image path (optional; if set, uses this instead of generated QR)
var IL_QR_IMAGE      = "QR.jpeg";
