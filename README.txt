== Description ==

jmail is an internal mailing tool for Moodle 2

It supports features like: labels(folders), attachments, reply, forward, print, read and unread messages, etc...

== Main features ==

* Send messages (TO, CC, BCC)

* Reply, forward

* Attachments in messages

* User names auto-completition when composing messages

* Bulk user selection

* Save attachments directly to your Moodle private files

* Drafts support

* Moodle HTML Editor integrated

* AJAX and Rich User Interface

* Quick search tool

* Ordering (date, subject, remitent)

* Pagination

* Labels (like folder, but a message can be linked to more than one label)

* Different types of mailboxes

* Receive copy of your messages to your profile external email account

* Direct access to all your mailboxes

* Groups support

* Message key shortcouts (reply, forward, delete and print)

* Global inbox

* Full backup and restore support


== Type of mailboxes ==

=== General mailbox ===

The default mailbox in a course. All the users cand send messages to others


=== Read only mailbox ===

Selected roles can only read messages (not reply or compose new messages)

Setup:
* [[Override permissions]] at block or course level for your targeted roles
* Set block/jmail:sendtomanagers to prevent
* Set block/jmail:sendtoall to prevent


=== Mailbox in approval mode ===

Moderators must approve messages sent by users

Moderators are the users with the permission block/jmail:approvemessages allowed

Setup:
Edit the block course settings, Check approval mode
* [[Override permissions]] at block or course level for your targeted roles
* Set block/jmail:approvemessages to allow for the users you want to be able to approve messages


=== Mailbox in restricted communication mode ====

Selected roles will be able to send messages only to managers (managers are also selected roles)

Managers are those with the permission block/jmail:sendtoall allowed

Setup:
* [[Override permissions]] at block or course level for your targeted roles
* Managers (teachers)
** Set block/jmail:sendtomanagers to allow
** Set block/jmail:sendtoall to allow
* Students
** Set block/jmail:sendtomanagers to allow
** Set block/jmail:sendtoall to prevent

=== Global inbox ===

Users will be able to send messages to any user in the system
Users will be able to see all their different courses messages in the same page
When a user replies to or forward a message, it will be delivery to the original remitent's course mailbox
When a user composes a new message, it will be delivery to the system global inbox

Setup:
* Go to Admin -> Plugins -> Blocks
* jmail General Settings
* Check enable global inbox

Please, note that at site levels all the users has the Authenticated User role by default, so if you want create specific managers you will have to create global roles.

== Installation ==

Follow [[Installing_plugins]] instructions


== Credits ==

2010 - 2012 Juan Leyva <http://twitter.com/#!/jleyvadelgado>

http://moodle.org/user/profile.php?id=49568


== See also ==

[https://github.com/jleyva/moodle-block_jmail Github page]

[[Category: Contributed code]]