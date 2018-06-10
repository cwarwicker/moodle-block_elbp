# Electronic Learning Blue Print (v1.4.0)

This plugin provides you with the Electronic Learning Blue Print block, which is an Individual Learning Plan system, which provides you with one centralised place to see all of the information relevant for a student, such as Attendance, Targets, Timetables, Registers, etc...


Features
------------
The PLP is feature-rich and allows you to customise almost every aspect of it, so that you can implement it in a way which works best for your institution.

Some of these features include:

* 10 Built-in plugins
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
	* BKSB Live
	* Timetable
	
* 2 Additional plugins when you use our Grade Tracker block
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
* Report - See overview reports and build up your own custom reports which can be exported to Excel or saved into the system for later comparisons. (Requires the bc_dashboard block)

Requirements
------------
- PHP 5.6 or higher
- Moodle 3.1 or higher
- block_bc_dashboard installed

Installation
------------
- Download the zip file, using the green "Clone or download" button
- Extract the files 
- Rename the folder inside "moodle-block_elbp-master" to just "elbp".
- Place the "elbp" folder inside the /blocks directory of your Moodle site and run through the normal plugin installation procedure, then you can add the block to a course page.

Licence
------------
http://www.gnu.org/copyleft/gpl.html
