# Registration & Customer Accounts

Dish Events uses standard WordPress user accounts for customer login, registration, and
booking history. Three shortcode-powered pages make up the account area. Accounts are
entirely optional — guest checkout can be left on so customers never need to register
— but when an account exists all past bookings are visible on the profile page.

---

## How accounts work

A WordPress user account is linked to a booking via the `dish_customer_user_id` meta key
(a WP user ID). Bookings placed by guests store `0` there. The profile page looks up
bookings by **email address first**, then by user ID, so if a guest later creates an
account using the same email all previous bookings become visible immediately.

---

## The three account pages

You need three pages in WordPress — one for each shortcode. Set their IDs in
**Dish Events → Settings → Pages** so the plugin can build correct links between them.

| Page purpose | Shortcode | Setting key |
|---|---|---|
| Sign in | `[dish_login]` | **Login Page** |
| Create an account | `[dish_register]` | **Register Page** |
| Booking history | `[dish_profile]` | **Profile Page** |

---

## Login page — `[dish_login]`

**For guests** the page renders a standard WordPress login form with:
- Email address or username field
- Password field
- "Remember me" checkbox
- A "Forgot your password?" link — handled entirely by WordPress, no plugin involvement
- A "Create one" link pointing at the Register page (only appears when WP registration
  is enabled in **Settings → General → Anyone can register**)

**For logged-in visitors** the form is replaced by a notice:  
*"You are logged in as [Name]. View your profile →"*  
(links to the Profile page set in settings)

After a successful login WordPress redirects back to the same page by default.

---

## Register page — `[dish_register]`

**Registration must be enabled in WordPress** — go to **Settings → General** and tick
**Anyone can register** — otherwise the page shows a "registration is not currently
available" notice regardless of any plugin setting.

When registration is open the form captures:
- **Username** — must be unique
- **Email address** — must be unique

WordPress emails a generated password to the address provided. There is no password
field on the form itself.

**Error display** — validation errors returned by WordPress after the form submission
are stored in a transient and shown on the next page load as a bulleted list above the
form.

**For logged-in visitors** the form is replaced by a notice linking to the Profile page.

A "Already have an account? Sign in" link at the bottom points back to the Login page.

---

## Profile page — `[dish_profile]`

**Requires the visitor to be logged in.** Guests see:  
*"Please sign in to view your profile."*  
with a link to the Login page.

For authenticated users the page shows:

- A welcome heading with their display name
- A chronological list of all their bookings — both upcoming and past — ordered by class
  date
- Per booking: class title (linked to the public class template), date/time, status badge,
  ticket count, total paid
- A **"View booking"** link per row — goes to the Booking Details page
  (`Settings → Pages → Booking Details Page`) with `?booking_id=X&key=Y` appended
- A **"Sign out"** link at the bottom that logs them out and returns to the profile page
  (which then shows the sign-in notice)

**What "all bookings" means** — the query finds any booking whose `dish_customer_email`
meta matches the account email *or* whose `dish_customer_user_id` matches the user ID.
This means bookings placed as a guest with the same email are automatically surfaced once
the customer logs in.

---

## Guest checkout

Controlled by **Dish Events → Settings → Features → Guest checkout**.

- **On (default):** customers can complete a booking by entering their name, email, and
  phone without creating or being logged in to an account. Their booking is saved with
  `dish_customer_user_id = 0`.
- **Off:** the checkout form requires a logged-in session. Guests are shown a login/register
  prompt instead of the booking form.

---

## Account creation at checkout

Controlled by **Dish Events → Settings → Features → Account creation at checkout**.

When this is on and the visitor is a guest, a collapsible "Create an account" section
appears at the bottom of the checkout form. It is always optional — the customer can
ignore it and complete the booking as a guest.

If the customer opts in they provide:
- **Username** — must be unique (validated before the booking is confirmed)
- **Password** — minimum 8 characters

What happens when they submit:
1. The booking is created first (the account creation is non-fatal — a failed account
   never blocks a valid booking).
2. `wp_create_user()` creates the WordPress account using the checkout email.
3. The customer's name is saved as their display name.
4. The new `user_id` is written to `dish_customer_user_id` on the booking post, linking it
   to the account.
5. The customer is logged in immediately via an auth cookie — they land on the
   confirmation page already authenticated.

> **Note:** account creation at checkout requires WP's **Anyone can register** setting
> to be on. If that setting is off the opt-in checkbox is hidden even when the feature
> toggle is enabled.

---

## Deleting an account

Customers can permanently delete their own account from the Profile page. The option is
tucked inside a **"Delete my account"** disclosure widget at the bottom of the page,
below the Sign out link — it stays collapsed until the customer deliberately opens it.

### What the customer sees

1. They open the disclosure and read a plain-language warning explaining what will happen.
2. They type their **email address** into a confirmation field to prove intent.
3. They click **"Permanently delete my account"**.
4. The button disables while the request processes, then they are logged out and
   redirected to the home page.

### What actually happens

| Data | Outcome |
|---|---|
| WordPress user account (username, password, display name) | **Deleted** |
| `dish_customer_name` on all linked bookings | Cleared to `''` |
| `dish_customer_phone` on all linked bookings | Cleared to `''` |
| `dish_customer_user_id` on all linked bookings | Reset to `0` |
| `dish_customer_email` on all linked bookings | **Retained** |
| Booking posts, statuses, ticket counts, revenue figures | **Retained** |

The booking email is kept deliberately. It is the shared reference that ties the studio's
booking record to the payment processor's transaction record (receipt, refund trail,
dispute evidence). Removing it would break that link. Everything else — the credential
and the personally identifying details — is gone.

Bookings linked by **both** user ID and email are found and anonymised, so bookings
placed as a guest before the account was created are also cleaned up.

### Who can use it

The delete option is **only shown to subscribers / customers** — any account with the
`edit_posts` capability (editors, admins) does not see the widget. The same guard is
enforced server-side so the AJAX endpoint rejects the request even if the HTML is
manipulated.

### Errors and edge cases

- **Wrong email** — the server compares using a timing-safe `hash_equals()` check.
  An incorrect email returns a field-level error without deleting anything.
- **Network failure** — the button re-enables and an error message is shown; nothing
  is deleted.
- **Account creation fails silently at checkout** — if account creation at checkout
  fails for any reason the booking is still valid and the customer can request deletion
  via this flow once they do have an account.

---

## Settings summary

| Location | Setting | Effect |
|---|---|---|
| Settings → General (WordPress) | **Anyone can register** | Gates `[dish_register]` form and the checkout opt-in |
| Dish Events → Settings → Pages | **Login Page** | Where `[dish_login]` is placed; used for all "sign in" links |
| Dish Events → Settings → Pages | **Register Page** | Where `[dish_register]` is placed; shown as "Create one" in login form |
| Dish Events → Settings → Pages | **Profile Page** | Where `[dish_profile]` is placed; linked from login form and booking confirmation |
| Dish Events → Settings → Features | **Guest checkout** | Allow bookings without a WP account |
| Dish Events → Settings → Features | **Account creation at checkout** | Show the optional username/password fields at the end of the checkout form |

---

## Gotchas

**WP registration gate is a hard requirement.** The plugin does not override
`get_option('users_can_register')`. If registration is disabled in WordPress, the
register form and the checkout account-creation checkbox both disappear automatically.

**Password reset uses WordPress natively.** The "Forgot your password?" link calls
`wp_lostpassword_url()` — it goes to the default WP password-reset flow at
`wp-login.php?action=lostpassword`. There is no custom dish-events template for this.

**Email uniqueness is enforced by WordPress.** If a guest already has a WP account
at the same email and tries to create another account at checkout, WordPress will
reject it silently (the account creation step fails but the booking still completes).
The customer can log in afterwards and the booking will appear in their profile because
of the email-match query.

**Display name vs username.** The register page captures a username; the checkout
account-creation path sets `display_name` and `first_name` to the checkout name field.
If a customer registers via `[dish_register]` their display name starts as their username
and they would need to update it in their WP profile — there is no "full name" field on
the standalone registration form.

**Booking Details Page must be configured.** The "View booking" links on the profile page
only appear if **Settings → Pages → Booking Details Page** is set. Without it the per-row
link is omitted entirely.
