=== Disable Users ===
Contributors: jaredatch
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=AD8KTWTTDX9JL
Tags: users, login, disable
Requires at least: 4.0.0
Tested up to: 4.3
Stable tag: trunk
License: GPLv2
 
Provides the ability to disable specific user accounts.

== Description ==

This plugin gives you the ability to disable specific user accounts via a profile setting.

Once installed and activated, a checkbox appears on the user profile settings (only for admins). When checked, the users account will be disabled and they will be unable to login with the account. If they try to login, they are instantly logged out and redirected to the login page with a message that notifies them their account is disabled.

This can be useful in a few situations.

* You are working on a site for a client who has an account, but do not want him to login and/or make changes during development.
* You have a client who has an unpaid invoice.

**[This plugin is on GitHub!](https://github.com/jaredatch/Disable-Users/)** Pull requests are welcome. If possible please report issues through Github.

== Installation ==

1. Upload `disable-users` to your `/wp-content/plugins/` directory.
1. Edit any user and then look for the "Disable User Account" checkbox.

== Frequently Asked Questions ==

= Can I change the message a disabled user sees? =

Yes, there is a filter in place for that, `ja_disable_users_notice`.

== Screenshots ==

1. User profile setting available to administrators.
2. Message when a disabled user tries to login.

== Changelog ==

= 1.0.5 (11/11/2015) =
* Added pl_PL transnation - Props Dominik Kocuj

= 1.0.4 (6/21/2015) =
* Bug fix for Disabled column to manage user screen

= 1.0.3 (6/15/2015) =
* Added Disabled column to manage user screen. Props @basteln3rk [(flexponsive)](https://www.flexponsive.net).
* Cleaned up code slightly and updated README information

= 1.0.2 =
* Removed accidental PHP short form

= 1.0.1 =
* Fixed notice that would show if WP_DEBUG was on. Props @vegasgeek.

= 1.0.0 =
* Initial launch