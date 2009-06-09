EventEx: Multiple section form submission for Symphony CMS
==========================================================

Version: 1.01
Author:  http://github.com/yourheropaul 


[CHANGE LOG]

1.0  - initial build
1.01 - fixed pcre.backtrack_limit workaround for filenames containing square brackets. 


[ABSTRACT]

Typically Symphony Events have been restricted to creating and modifying entries in a single section, a conceptual limitation that, amongst other minor things, stunts the creation of complex, single-page forms. EventEx (named rather dissapointingly for its position in the programmatic hierarchy: EXtending the Event class) aims to provide more power and flexibility to user input, especially in conjunction with other utilities, especially Form Controls (http://github.com/nickdunn/form-controls/). One of the objectives of the system is to empower front-end developers by creating an HTML-compliant, semantic markup that harnesses as much of Symphony's power as possible; another, arguably more pressing, influence is the tediousness of creating and interpreting complex forms without having to write large chunks of messy custom code.


[INSTALLATION]

** Note: The newest version is available at http://github.com/yourheropaul/eventex/tree/master
** Note also: EventEx has a dependency - DatabaseManipulator (http://github.com/yourheropaul/databasemanipulator) - which in turn has a dependency: ASDC (http://github.com/pointybeard/asdc). Both are small, unobtrusive Symphony extensions, and extremely useful in their own right.

1. Place the 'eventex' directory in the Symphony 'extensions' directory.
2. Enable the extension in the 'System/Extensions' menu of the Symphony back-end.


[OVERVIEW: A CASE STUDY]

EventEx specifics can get confusing, so it's best to start with an example. Imagine a Symphony build, perhaps not so different from the one you are currently working on, which has will allow users to login and contribute to the site content. There are two kinds of users: Musicians, who can upload media files as well as comment on uploaded media files, and Fans, who can comment on media files but not upload them. It's no so difficult to believe that you might have built a login system that identifies users, but further imagine that there are significant data storage requirements for Musicians and Fans, so they'll require separate Symphony sections; also, for whatever reason you care to invent, the login system is most efficient when it uses a single section to lookup data, meaning that you either have to replicate the email address, password and other related fields (like a cookie token or preferences) in both sections or, more efficiently, create three sections: Users, Musicians and Fans.

Peachy, except that your user registration form has hit a glass ceiling - information is now spread beyond the scope of the fields[] array. EventEx introduces a slight syntactical change (it should be noted that EventEx does not modify existing Events) in the form markup.  Where before you might have used a markup like:

<form action="" method="post">
  username: <input type="text" name="fields[name]" />
  password: <input type="text" name="fields[password]" />

  <input type="submit" name="action[create-musician]">
</form>

EventEx uses a more symbolic approach, referring to the section handle in the form control:

<form action="" method="post">
  username: <input type="text" name="musicians[name]" />
  password: <input type="text" name="users[password]" />

  <input type="submit" name="action[create-musician]">
</form>

Of course, both examples assume that both the Musicians and Users have only one required field (there's also an issue of security, which is covered in the [USE AND INTEGRATION] segment.) The EventEx form above will create a new entry in each section with the details provided, which is nice but rather useless - how are the two sections going to be linked together? The most common approach is the Select Box Link field (now part of the Symphony core) which will in this case be called 'Musician Entry' and attached the to Users section, under the assumption that the login functionality will use it to find the associated Musician or Fan entry. A single line of markup will suffice:

<input type="hidden" name="users[musician-entry]" value="musicians[system:id]" />

When a new entry is created, the system ID is logged, and can be used to auto-populate other field values (in EventEx, 'system:id' is a reserved dynamic field name.) The value can actually contain any combination of string and section-handle[field-handle]s, so, imagining there's a field called 'Password reminder' the the Musicians section, this would be perfectly valid and do what you'd imagine:

<form action="" method="post">
  username: <input type="text" name="musicians[name]" />
  password: <input type="text" name="users[password]" />

  <input type="hidden" name="musicians[password-reminder]" value="Your password is users[password]" />
    
  <input type="hidden" name="users[musician-entry]" value="musicians[system:id]" />

  <input type="submit" name="action[create-musician]">
</form>

Note that the order of the field is irrelevant from a processing perspective; there's a two-pass system in place to ensure that order-of-approach is a trivial issue. There's significantly more to the EventEx engine, but the only other thing that's used ubiquitously is the redirection override. Where in regular events one might use:

<input type="hidden" name="redirect" value="/path/to/url" />

We can now use parse-redirect in a similar way, except taking into account the string parsing system noted above, it becomes more interesting:

<input type="hidden" name="parse-redirect" value="/members/musicians[name]/" />

In the case of redirects, all values are transformed into URL-friendly handles in the usual Symphony way. There are several other issues that EventEx squeals though like heated buzzsaw.


[USE AND INTEGRATION]

1. CREATING THE EVENT
---------------------

Future versions will doubtless feature a Symphony back-end manager, but such niceties have been sacrificed in order to get the main features off the ground and stable. The only real downside of EventEx, therefore, is that all events must be custom (which is to say that they have to be modified manually.) Use the normal Symphony back-end to create an event, then, and modify the resultant .php file. There are several differences between standard Events and EventEx-powered Events at the code level:

1. The class.eventex.php file is required, and usually resides at '{EXTENSIONS}/eventex/lib/class.eventex.php'.
2. The class definition extends 'EventEx', not 'Event'
3. Some static methods (allowEditorToParse(), documentation() and load() ) are declared in the parent class, and usually don't need to be overloaded.
4. The method getSource() defaultly returns a system ID string, but given that EventEx deals in section name handles, the best known practise is to have the method return an array of said handles: this consolidates the affected sections and help maintain the original workflow.
5. Class constants like ROOTELEMENT, as well as system member variables like $eParamFILTERS, are ignored by the EventEx core. The root node of resultant XML is determined by the dissecting and un-handlising the class name (see point 2 below.)
6. Instead of including the event processing file ("include(TOOLKIT . '/events/event.section.php');") an extended Event calls a protected method called updateNamedSections(), the argument for which is an array of section handles to process.

Now that it's written down, the above points are rather dull.  Here, then, is a perfectly functional extended Event:

require_once(EXTENSIONS . '/eventex/lib/class.eventex.php');

Class eventupdate_two_sections extends EventEx
{			
	// Should return an array of section handles
	public static function getSource()
	{
		return array("musicians", "users");
	}
		
	public static function about(){
		return array(
				 'name' => 'Update Two Sections',
				 'author' => array(
						'name' => 'Yo\' Momma',
						'website' => 'http://symphony2',
						'email' => 'email.address@server'),
				 'version' => '1.0',
				 'release-date' => '2009-01-21T10:41:52+00:00',
				 'trigger-condition' => 'action[update-two-sections]');	
	}
	
	protected function __trigger()
	{				
		// this returns a Symphony XMlElement object
		$result = $this->updateNamedSections(self::getSource());		
		
		return $result;
	}		
}

You'll notice that the static getSource() method only includes two section handles. It could include any number and will ignore any that aren't present in the submitting form's POST array; that is to say that it could easily process both form submissions from both the Musicians' and Fans' registration pages (from the example above) if 'fans' were added to the array.

One other thing to note is that in the rare event of system-level failure, EventEx throws standard PHP Exceptions, which in the above code and uncaught. Actual user error will never result in this behaviour.

2. HANDLING THE RESPONSE XML
----------------------------

All of the standard Symphony processing power is kept in tact, and the only difference in resultant XML is that there will be an <entry> node for each created entry in a section. XML will look something like:
	
<events>
	<update-two-sections>
		<entry id="653" result="success" type="created" section-id="15" section-handle="musicians">
			<message>Entry created successfully.</message>
			<post-values>
				<name>here is my name</version-number>
			</post-values>
		</entry>
		<entry result="error" section-id="14" section-handle="users">
			<message>Entry encountered errors when saving.</message>
			<password type="missing" />
		</entry>
	</update-two-sections>
</events>

This XML tree can then be processed using XSLT as you desire, but once again I call your attention to Form Controls (http://github.com/nickdunn/form-controls/), which simplifies the process massively, and is designed with EventEx in mind. Like everything else related to forms, this part can get complicated, and EventEx handles most problems elegantly (see [TRANSACTIONAL MODEL] below.)

3. RETROFITTING OLD EVENTS
--------------------------

Standard Symphony events can be upgraded simply by calling the updateNamedSections() method, as described above. However, the the submitting form's markup must in each case match the EventEx notation - ie.:

<input name="section-handle[field]" />


[MORE SOPHISTICATED SYNTAX]

1. HYPHENS IN FORM INPUT VALUES
-------------------------------

Section handles can contain hyphens, but you might like to include hyphens for other purposes in string.  It is therefore possible to escape then with a backslash:

<input type="hidden" name="versions[version-number]" value="0\-pages[title]\-pages[system:id]" />	

Note that this only becomes relevant when the hyphen is directly before the section handle - other hyphens do not need to be escaped.

2. CONCATENATION OF SUB-VALUES
-----------------------------

One time saviour and now candidate for deprication, this underused functionality is mostly used for date selection. The system will automatically concatenate sub-values in the POST array.  The concept is best illustrated by example:

<select name="users[date-of-birth][day]">
	<!-- .. list of days -->
</select>

<select name="users[date-of-birth][month]">
	<!-- .. list of months -->
</select>

<select name="users[date-of-birth][year]">
	<!-- .. list of years -->
</select>

If the 'date of birth' field is of type 'Date' it will be evaluated as "[day]-[month]-[year]"; in other cases it would be "[day] [month] [year]" (no hyphens) -- in both cases replacing the values for the actual submitted form values. Form Controls (http://github.com/nickdunn/form-controls/) doesn't, at the time of writing, support this feature, and has a much more elegant and user-friendly system in place.


[TRANSACTIONAL MODEL]

An early problem was that in the case of one entry failing to submit to to missing or invalid fields, the other entries were still inserted in the database, leading to all sorts of chaos (the implications for unique fields like email addresses were frightful -- Nick (Nick Dunn: author of Form Controls (http://github.com/nickdunn/form-controls/) actually wept on more than one occasion) and a bloated database. All entries in forms are now required, and if entry fails, any previously submitted are rolled back.

To help prevent form-builder suicide and self harm, the resultant XML includes all submitted post values, such that the form can be rebuilt as if only one section were being submitted to.


[MULTIPLE ENTRIES]

Following the Symphony precedent, numeric predicates in form input names are respected:

username 1: <input type="text" name="musicians[1][name]" />
username 2: <input type="text" name="musicians[2][name]" />

A current, soon-to-be-resolved limitation of the system is that value substitutions cannot reference by predicate, so:

username 1: <input type="hidden" name="users[1][selectbox-link]" value="musicians[system:id]" />
username 2: <input type="hidden" name="users[2][selectbox-link]" value="musicians[system:id]"/>

will link user [1] to musician [1] and user [2] to musician [2], but the following will not (yet) work:

username 1: <input type="hidden" name="users[1][selectbox-link]" value="musicians[2][system:id]" />


[EDITING ENTRIES]

The existing Symphony statute is to provide a form field called 'id':

<input type="hidden" name="id" value="1234" />

which will, on Event firing, edit entry number 1234 rather than create a new one. That model is flawed when it comes to multiple sections and muleiple entries, however, so the more XML-like 'system:id'. Both of these will work as expected:

<input type="hidden" name="users[system:id]" value="1234" />
<input type="hidden" name="users[1][system:id]" value="1234" />


[KNOWN ISSUES WHAT ARE BEING DEALT WITH SO SHUT UP]

- providing a section handle in the Event PHP array for a section that doesn't exist causes a crash.
- the parse-redirect is called as soon as the form is processed, and ideally it would also feature a callback
- updateNamedSections doesn't allow an XMLElement reference
- value substitutions don't accept numeric predicates

[ABOUT MARKDOWN]

I wanted to use it, I really did. I still do. It turns out, however, there is no machine with a large enough display or output to gauge my joy a Nick's (Nick Dunn: author of Form Controls (http://github.com/nickdunn/form-controls/) emo anguish at me not doing so, and therefore I will not until he retracts his raging Markdown boner.
 
