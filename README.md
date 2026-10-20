# HudumaLink — Public Service Request System

Citizens submit service requests (broken streetlight, water issue, garbage collection, bursary, and any extras an administrator adds). Staff process the queue. Administrators manage users at different access levels and can add or remove services.

## Setup

1. Start Apache and MySQL in XAMPP.
2. Visit `http://localhost/hudumalink/install.php`.
3. Click **Run installation**. If this database still has the older passport/ID catalogue, also click **Update catalogue to civic requests**.
4. Sign in, then protect or delete `install.php`.

## Demo accounts

| Access level | National ID | Password |
| --- | --- | --- |
| Administrator | `00000001` | `Admin@123` |
| Department officer (street lighting) | `00000002` | `Officer@123` |
| Supervisor | `00000003` | `Supervisor@123` |
| Citizen | `12345678` | `Citizen@123` |

## Access levels

- **Citizen** — submit and track their own requests
- **Department officer** — process requests in one department
- **Supervisor** — process requests in every department
- **Administrator** — users, access levels, add/remove services, all requests

Administrators open **Users & access**, **Services**, and **Email & SMS** in the staff console.

## Email and SMS progress alerts

Citizens are notified in the portal, by email, and by SMS when a request is submitted, paid, or updated by staff.

1. Open `config/config.php`.
2. For **email**, set `MAIL_USER` and `MAIL_PASS` (Gmail: use an [App Password](https://myaccount.google.com/apppasswords) with `smtp.gmail.com`, port 587, `tls`).
3. For **SMS**, create an [Africa's Talking](https://account.africastalking.com/) app. Put the API key in `AT_API_KEY`. Keep `AT_SANDBOX` as `true` while testing, and add the citizen’s phone to the sandbox.
4. Send a test from **Staff console → Email & SMS**.

If credentials are empty, alerts still appear in the portal and a delivery log records that email/SMS was skipped.
