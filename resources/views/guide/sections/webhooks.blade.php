<h2 class="h4 fw-bold mb-1">Webhooks</h2>
<p class="text-muted small mb-4">URL: <code>/webhooks</code> &nbsp;|&nbsp; Permission: <code>federation.create</code> (Admin only)</p>

<p>Webhooks deliver HTTP POST notifications to external systems when key events occur in the registry.</p>

<h5 class="fw-semibold mt-4 mb-2">Creating a Webhook Endpoint</h5>
<p>Click <strong>New Webhook</strong>. Fill in the target URL (must use HTTPS), select which event types to subscribe to, and set Active. A <strong>secret</strong> is auto-generated on creation — store it securely, it cannot be retrieved again. Use <strong>Regenerate</strong> to rotate it.</p>

<h5 class="fw-semibold mt-4 mb-2">Event Types &amp; Payloads</h5>
<p class="small text-muted">Every delivery is a JSON POST. All payloads include <code>event</code> and <code>timestamp</code> fields.</p>

<p class="mb-1 small fw-semibold"><code>entity.created</code> — fired when a new entity is registered</p>
<pre class="bg-dark text-light p-3 rounded small mb-3">{
  "event": "entity.created",
  "timestamp": "2026-05-09T14:22:01+00:00",
  "entity_id": "https://sp.example.org/shibboleth",
  "type": "SP",
  "status": "active"
}</pre>

<p class="mb-1 small fw-semibold"><code>entity.approved</code> — fired when an entity is approved into a federation</p>
<pre class="bg-dark text-light p-3 rounded small mb-3">{
  "event": "entity.approved",
  "timestamp": "2026-05-09T14:25:10+00:00",
  "entity_id": "https://sp.example.org/shibboleth",
  "federation_id": "019de9d2-c2ba-7357-bb4c-2daabeec6595",
  "federation": "https://federation.example.org"
}</pre>

<p class="mb-1 small fw-semibold"><code>metadata.generated</code> — fired when a federation metadata aggregate is (re)generated</p>
<pre class="bg-dark text-light p-3 rounded small mb-3">{
  "event": "metadata.generated",
  "timestamp": "2026-05-09T15:00:03+00:00",
  "federation_id": "019de9d2-c2ba-7357-bb4c-2daabeec6595",
  "federation_uri": "https://federation.example.org"
}</pre>

<h5 class="fw-semibold mt-4 mb-2">HTTP Request Format</h5>
<p class="small text-muted">The outgoing request looks like this:</p>
<pre class="bg-dark text-light p-3 rounded small mb-3">POST https://your-system.example.org/hooks/registry HTTP/1.1
Content-Type: application/json
X-Hub-Signature-256: sha256=3b5e82f1a4c...

{"event":"entity.created","timestamp":"2026-05-09T14:22:01+00:00",...}</pre>

<h5 class="fw-semibold mt-4 mb-2">Verifying the Signature</h5>
<p class="small text-muted">Compute HMAC-SHA256 of the raw request body using your endpoint secret and compare to the header value. This follows the GitHub webhook signature convention.</p>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#sig-php">PHP</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sig-python">Python</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sig-node">Node.js</button></li>
</ul>
<div class="tab-content mb-4">
    <div class="tab-pane fade show active" id="sig-php">
<pre class="bg-dark text-light p-3 rounded small">$body    = file_get_contents('php://input');
$secret  = 'your-endpoint-secret';
$header  = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$expected = 'sha256=' . hash_hmac('sha256', $body, $secret);

if (!hash_equals($expected, $header)) {
    http_response_code(401);
    exit('Invalid signature');
}

$payload = json_decode($body, true);
// handle $payload['event'] ...</pre>
    </div>
    <div class="tab-pane fade" id="sig-python">
<pre class="bg-dark text-light p-3 rounded small">import hmac, hashlib

def verify(body: bytes, secret: str, header: str) -> bool:
    expected = 'sha256=' + hmac.new(
        secret.encode(), body, hashlib.sha256
    ).hexdigest()
    return hmac.compare_digest(expected, header)</pre>
    </div>
    <div class="tab-pane fade" id="sig-node">
<pre class="bg-dark text-light p-3 rounded small">const crypto = require('crypto');

function verify(body, secret, header) {
  const expected = 'sha256=' +
    crypto.createHmac('sha256', secret).update(body).digest('hex');
  return crypto.timingSafeEqual(
    Buffer.from(expected), Buffer.from(header)
  );
}</pre>
    </div>
</div>

<h5 class="fw-semibold mt-4 mb-2">Delivery History</h5>
<p>The webhook show page lists all delivery attempts with status (<span class="badge bg-success">delivered</span> / <span class="badge bg-danger">failed</span> / <span class="badge bg-warning text-dark">pending</span>), HTTP response code, and timestamp.</p>
<p><strong>Retry</strong> re-dispatches a failed delivery immediately. Failed deliveries are automatically retried with exponential backoff: 1 min → 2 min → 5 min → 10 min → 30 min.</p>
