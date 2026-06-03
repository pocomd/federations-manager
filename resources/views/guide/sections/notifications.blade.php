<h2 class="h4 fw-bold mb-1">Notifications</h2>
<p class="text-muted small mb-4">URL: <code>/notifications</code> &nbsp;|&nbsp; Preferences: <code>/profile/notifications</code></p>

<h5 class="fw-semibold mt-4 mb-2">Notification Bell</h5>
<p>The bell icon in the top navigation bar shows your unread notification count. Click it for the last 5 unread notifications or <strong>View all</strong> to open the full page.</p>

<h5 class="fw-semibold mt-4 mb-2">Notifications Page</h5>
<p>Actions per notification: <strong>Mark read</strong>, <strong>Archive</strong> (removes from main view), <strong>Go to</strong> (navigates to the related record). <strong>Mark All Read</strong> clears all unread indicators at once.</p>
<p>Archived notifications are accessible at <code>/notifications/archive</code>.</p>

<h5 class="fw-semibold mt-4 mb-2">Events That Generate Notifications</h5>
<table class="table table-sm table-bordered">
    <thead class="table-light"><tr><th>Event</th><th>Who is notified</th></tr></thead>
    <tbody>
        <tr><td>Entity pending approval</td><td>Federation Managers</td></tr>
        <tr><td>Entity approved</td><td>Entity owner</td></tr>
        <tr><td>Entity rejected</td><td>Entity owner (with reason)</td></tr>
        <tr><td>Entity suspended</td><td>Entity owner</td></tr>
        <tr><td>Certificate expiring / expired</td><td>Entity owner</td></tr>
        <tr><td>User registered</td><td>Admins</td></tr>
        <tr><td>Invitation request created</td><td>Federation Managers</td></tr>
        <tr><td>Invitation request approved/rejected</td><td>Entity Manager who requested</td></tr>
        <tr><td>Metadata generated</td><td>Federation Managers</td></tr>
        <tr><td>Federation deactivated</td><td>Admins</td></tr>
    </tbody>
</table>

<h5 class="fw-semibold mt-4 mb-2">Notification Preferences</h5>
<p>URL: <code>/profile/notifications</code> — each user can toggle <strong>In-app</strong> and <strong>Email</strong> delivery per notification type. Changes take effect immediately for future notifications.</p>
