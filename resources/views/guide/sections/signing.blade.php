<h2 class="h4 fw-bold mb-1">Metadata Signing (xmlsectool)</h2>
<p class="text-muted small mb-4">Required for generating signed federation metadata aggregates.</p>

<div class="alert alert-info small mb-4">
    <i class="bi bi-info-circle me-1"></i>
    This is optional if you do not publish signed metadata. All other functionality works without it.
</div>

<h5 class="fw-semibold mt-4 mb-2">How Signing Keys Work</h5>
<p class="small text-muted mb-2">Each federation can have its own private key and certificate uploaded directly through the UI — no filesystem access needed. When metadata is generated for a federation, the app uses that federation's uploaded key pair. If no per-federation pair is uploaded, a global fallback pair configured in <code>.env</code> is used instead.</p>

<table class="table table-sm table-bordered small mb-4">
    <thead class="table-light"><tr><th>Method</th><th>Where</th><th>Recommended for</th></tr></thead>
    <tbody>
        <tr>
            <td><strong>Per-federation upload (UI)</strong></td>
            <td>Federation → Signing Keys tab</td>
            <td>All normal deployments — operators can manage keys without server access</td>
        </tr>
        <tr>
            <td><strong>Global fallback (<code>.env</code>)</strong></td>
            <td><code>FEDERATION_SIGNING_KEY</code> / <code>FEDERATION_SIGNING_CERT</code></td>
            <td>Single-federation setups or a shared fallback when no per-federation key is uploaded</td>
        </tr>
    </tbody>
</table>

<h5 class="fw-semibold mt-4 mb-2">Uploading Keys via the UI</h5>
<p class="small text-muted mb-2">Go to <strong>Federations → [federation name] → Signing Keys</strong> tab. Upload a single file and the app detects the content automatically:</p>
<ul class="small">
    <li><strong>PEM file containing a private key only</strong> (<code>.key</code>, <code>.pem</code>) — saved as the signing key; you will be prompted for the certificate next</li>
    <li><strong>PEM file containing a certificate only</strong> (<code>.crt</code>, <code>.pem</code>, <code>.cer</code>) — saved as the certificate; you will be prompted for the key next</li>
    <li><strong>PEM file containing both</strong> — both are extracted and saved in one step</li>
    <li><strong>PKCS#12 bundle</strong> (<code>.p12</code>, <code>.pfx</code>) — both are extracted and saved in one step; enter the bundle password if it is encrypted</li>
</ul>
<p class="small text-muted">Keys are always stored unencrypted at rest with <code>0600</code> permissions under <code>storage/app/signing-keys/</code>. The app verifies that the private key and certificate form a matching pair before accepting them.</p>

<h5 class="fw-semibold mt-4 mb-2">Generate a Self-Signed Key Pair (for testing)</h5>
<p class="small text-muted">Run this on any machine with OpenSSL, then upload the resulting files via the UI:</p>
<pre class="bg-dark text-light p-3 rounded small">openssl req -x509 -nodes -newkey rsa:4096 -days 3650 \
  -keyout signing.key \
  -out    signing.crt \
  -subj   "/CN=Federation Metadata Signing"</pre>
<p class="small text-muted">Upload <code>signing.key</code> first (or combine both steps by uploading a PKCS#12 bundle).</p>

<h5 class="fw-semibold mt-4 mb-2">1. Install Java</h5>
<p class="small text-muted">xmlsectool requires Java 11 or later.</p>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#java-ubuntu">Ubuntu / Debian</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#java-centos">CentOS / RHEL</button></li>
</ul>
<div class="tab-content mb-4">
    <div class="tab-pane fade show active" id="java-ubuntu">
        <pre class="bg-dark text-light p-3 rounded small">sudo apt-get install -y openjdk-17-jre-headless</pre>
    </div>
    <div class="tab-pane fade" id="java-centos">
        <pre class="bg-dark text-light p-3 rounded small">sudo dnf install -y java-17-openjdk-headless</pre>
    </div>
</div>

<h5 class="fw-semibold mt-4 mb-2">2. Install xmlsectool</h5>
<pre class="bg-dark text-light p-3 rounded small">cd /tmp
curl -LO https://shibboleth.net/downloads/tools/xmlsectool/latest/xmlsectool-3.0.0-bin.zip
unzip xmlsectool-3.0.0-bin.zip
sudo mv xmlsectool-3.0.0 /opt/xmlsectool
sudo ln -s /opt/xmlsectool/xmlsectool.sh /usr/local/bin/xmlsectool
sudo chmod +x /usr/local/bin/xmlsectool
xmlsectool --version</pre>

<h5 class="fw-semibold mt-4 mb-2">3. Configure Signing Keys</h5>
<p class="small text-muted mb-2">
    <strong>Recommended:</strong> upload keys through the UI — no server access needed (see <em>Uploading Keys via the UI</em> above).
</p>
<p class="small text-muted mb-2">
    <strong>Optional global fallback</strong> — if you prefer a single shared key pair for all federations, place the files on the server and set:
</p>
<pre class="bg-dark text-light p-3 rounded small">FEDERATION_SIGNING_KEY=/etc/federation/signing.key
FEDERATION_SIGNING_CERT=/etc/federation/signing.crt</pre>
<p class="small text-muted mb-2">To generate the files on the server:</p>
<pre class="bg-dark text-light p-3 rounded small">sudo mkdir -p /etc/federation
sudo openssl req -x509 -nodes -newkey rsa:4096 -days 3650 \
  -keyout /etc/federation/signing.key \
  -out    /etc/federation/signing.crt \
  -subj   "/CN=Federation Registry Metadata Signing"
sudo chmod 600 /etc/federation/signing.key
sudo chown www-data:www-data /etc/federation/signing.*</pre>
<p class="small text-muted">On CentOS/RHEL replace <code>www-data</code> with <code>nginx</code> or <code>apache</code> depending on your web server.</p>

<h5 class="fw-semibold mt-4 mb-2">4. Set the Binary Path</h5>
<p class="small text-muted">Add the binary path to your <code>.env</code> file (this is always required):</p>
<pre class="bg-dark text-light p-3 rounded small">XMLSECTOOL_PATH=/usr/local/bin/xmlsectool</pre>

<h5 class="fw-semibold mt-4 mb-2">5. Test Signing Manually</h5>
<p class="small text-muted mb-2">
    Create a minimal metadata file and sign it to confirm the key, certificate, and binary all work together
    before relying on the application to sign production metadata.
</p>

<p class="small mb-1 fw-semibold">Create a test input file</p>
<pre class="bg-dark text-light p-3 rounded small mb-3">cat &gt; /tmp/test-metadata.xml &lt;&lt; 'EOF'
&lt;?xml version="1.0" encoding="UTF-8"?&gt;
&lt;md:EntitiesDescriptor
    xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata"
    xmlns:ds="http://www.w3.org/2000/09/xmldsig#"
    Name="https://registry.example.com"
    ID="_test001"&gt;
&lt;/md:EntitiesDescriptor&gt;
EOF</pre>

<p class="small mb-1 fw-semibold">Sign it</p>
<pre class="bg-dark text-light p-3 rounded small mb-3">xmlsectool \
  --sign \
  --digest SHA-256 \
  --referenceIdAttributeName ID \
  --inFile  /tmp/test-metadata.xml \
  --outFile /tmp/test-metadata-signed.xml \
  --keyFile /etc/federation/signing.key \
  --certificate /etc/federation/signing.crt</pre>

<p class="small mb-1 fw-semibold">Expected output (exit 0)</p>
<pre class="bg-dark text-light p-3 rounded small mb-3">INFO  XMLSecTool - Reading XML document from file '/tmp/test-metadata.xml'
INFO  XMLSecTool - XML document parsed and is well-formed.
INFO  XMLSecTool - XML document successfully signed
INFO  XMLSecTool - XML document written to file '/tmp/test-metadata-signed.xml'</pre>

<p class="small mb-1 fw-semibold">Inspect the signature in the output</p>
<pre class="bg-dark text-light p-3 rounded small mb-3">grep -o '&lt;ds:SignatureMethod[^/]*/&gt;' /tmp/test-metadata-signed.xml
# → &lt;ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/&gt;

grep -o '&lt;ds:DigestMethod[^/]*/&gt;' /tmp/test-metadata-signed.xml
# → &lt;ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/&gt;</pre>

<div class="alert alert-warning small mb-4">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Common mistake:</strong> do not use <code>--key</code> or <code>--signatureAlg</code> — those are not valid flags and will cause xmlsectool to exit immediately with an error.
    The correct flags are <code>--keyFile</code> for the private key and <code>--digest SHA-256</code> for the algorithm (which controls both the digest and RSA signature algorithm).
</div>

<h5 class="fw-semibold mt-4 mb-2">6. Verify via Self-Test</h5>
<p class="small text-muted">Once manual signing works, confirm the application picks up the config:</p>
<pre class="bg-dark text-light p-3 rounded small">php artisan app:selftest</pre>
<p class="small text-muted">Or check <strong>Admin → System Health</strong> — the <em>XML Signing (xmlsectool)</em> row should show <span class="text-success fw-semibold">OK — Signing round-trip successful</span>.</p>
