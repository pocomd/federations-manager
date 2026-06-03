<h2 class="h4 fw-bold mb-1">Attribute Definitions</h2>
<p class="text-muted small mb-4">URL: <code>/attributes</code></p>

<p>The global library of SAML attributes. Used across the system for SP requested attributes and IdP attribute release policies (ARP).</p>

<h5 class="fw-semibold mt-4 mb-2">Attribute Fields</h5>
<table class="table table-sm table-bordered">
    <thead class="table-light"><tr><th>Field</th><th>Description</th></tr></thead>
    <tbody>
        <tr><td>Name</td><td>Machine-readable identifier, e.g. <code>eduPersonPrincipalName</code></td></tr>
        <tr><td>Friendly Name</td><td>Human label, e.g. <em>eduPerson Principal Name</em></td></tr>
        <tr><td>OID</td><td>URN OID, e.g. <code>urn:oid:1.3.6.1.4.1.5923.1.1.1.7</code></td></tr>
        <tr><td>URN</td><td>MACE URN, e.g. <code>urn:mace:dir:attribute-def:eduPersonEntitlement</code></td></tr>
        <tr><td>Description</td><td>What the attribute represents</td></tr>
        <tr><td>Active</td><td>Inactive attributes are hidden from selection dropdowns but not deleted</td></tr>
    </tbody>
</table>

<h5 class="fw-semibold mt-4 mb-2">Deactivate vs Delete</h5>
<p>Deleting an attribute that is in use (referenced by SP requested attributes or ARP entries) is blocked — the system automatically deactivates it instead. Reactivate it any time by toggling the Active switch.</p>

<h5 class="fw-semibold mt-4 mb-2">Where Attributes Are Used</h5>
<ul>
    <li><strong>SP Requested Attributes</strong> — <code>/entities/{id}/requested-attributes</code> — list of attributes an SP requests from IdPs via <code>&lt;md:RequestedAttribute&gt;</code></li>
    <li><strong>IdP ARP</strong> — <code>/entities/{id}/arp</code> — defines which attributes an IdP releases to each connected SP</li>
    <li><strong>Federation Required Attributes</strong> — Attributes tab on a federation's show page</li>
</ul>
