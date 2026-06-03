<h2 class="h4 fw-bold mb-1">Mail Templates</h2>
<p class="text-muted small mb-4">URL: <code>/mail/templates</code> &nbsp;|&nbsp; Permission: <code>federation.edit</code></p>

<p>Mail templates define reusable email subjects and bodies with <code>[[placeholder]]</code> substitution. Used when sending bulk emails to federation members.</p>

<h5 class="fw-semibold mt-4 mb-2">Available Placeholders</h5>
<table class="table table-sm table-bordered">
    <thead class="table-light"><tr><th>Placeholder</th><th>Replaced with</th></tr></thead>
    <tbody>
        <tr><td><code>[[entity_name]]</code></td><td>Entity display name</td></tr>
        <tr><td><code>[[entity_id]]</code></td><td>Entity ID URI</td></tr>
        <tr><td><code>[[contact_name]]</code></td><td>Contact's full name</td></tr>
        <tr><td><code>[[contact_email]]</code></td><td>Contact's email address</td></tr>
        <tr><td><code>[[federation_name]]</code></td><td>Federation name</td></tr>
        <tr><td><code>[[federation_uri]]</code></td><td>Federation URI</td></tr>
        <tr><td><code>[[mail_signature]]</code></td><td>Value of <code>mail_signature</code> system preference</td></tr>
        <tr><td><code>[[expiry_date]]</code></td><td>Certificate expiry date</td></tr>
        <tr><td><code>[[days_remaining]]</code></td><td>Days until certificate expires</td></tr>
    </tbody>
</table>

<p>On the create/edit form, click any placeholder badge to insert it at the cursor position in the subject or body field. The <strong>Preview</strong> button renders the template with sample data.</p>

<h5 class="fw-semibold mt-4 mb-2">Template Groups</h5>
<p>Templates are organised into groups: <code>general</code>, <code>certificate</code>, <code>invitation</code>, <code>approval</code>. The group is used to filter templates when composing a federation email.</p>

<h5 class="fw-semibold mt-4 mb-2">Using Templates</h5>
<p>To send an email using a template, go to a federation's show page and click <strong>Send Email</strong>. Select the template, filter by entity type and contact type, then send. Delivery is queued and processed asynchronously.</p>
