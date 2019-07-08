THIS BRANCH IS IN DEVELOPMENT, DO NOT USE


# Electronic Learning Blue Print (v1.4.2)

This plugin provides you with the Electronic Learning Blue Print block, which is an Individual Learning Plan system, which provides you with one centralised place to see all of the information relevant for a student, such as Attendance, Targets, Timetables, Registers, etc...

![](https://image.ibb.co/deKKOo/screenshot.png)


Features
------------
The PLP is feature-rich and allows you to customise almost every aspect of it, so that you can implement it in a way which works best for your institution.

Some of these features include:

* 9 Built-in plugins
	* Attendance & Punctuality
	* Register
	* Targets
	* Tutorials
	* Comments
	* Course Reports
	* Attachments
	* Challenges
	* Learning Styles
	* Additional Support
	* Timetable

* 2 Additional plugins when you use the Grade Tracker block
	* Grade Tracker
	* Prior Learning

* Customise - Build your own custom plugins from 5 available types
	* Single Reports - Static forms. One per student.
	* Incremental Reports - A table of information, entered row by row.
	* Multiple Reports - Create multiple instances of the same report, with different values.
	* Internal DB - A read-only report, drawing data out of your Moodle database, as defined by an SQL query
	* External DB - A read-only report, drawing data out of an external database, as defined by an SQL query

* MIS Integration - Display data direct from your MIS/SIS system. Supports the following data sources:
	* MySQL
	* SQL Server
	* PostgreSQL
	* Oracle
	* Firebird
	* Microsoft Access
	* SQLite

* Email Alerts - Set up custom alerts to suit your particular needs, based on PLP events, against whole courses, course groups or individual students.
* Report - See overview reports and build up your own custom reports which can be exported to Excel or saved into the system for later comparisons.

Requirements
------------
- PHP 5.6 or higher
- Moodle 3.1, 3.2, 3.3
- block_bc_dashboard installed

Installation
------------
- Download the zip file, using the green "Clone or download" button
- Extract the files
- Rename the folder inside "moodle-block_elbp-master" to just "elbp".
- Place the "elbp" folder inside the /blocks directory of your Moodle site and run through the normal plugin installation procedure, then you can add the block to a course page.

User Guide
------------
[Please see the Wiki](https://github.com/cwarwicker/moodle-block_elbp/wiki)

Licence
------------
http://www.gnu.org/copyleft/gpl.html
