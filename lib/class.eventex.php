<?php

require_once(TOOLKIT . '/class.event.php');
require_once(TOOLKIT . '/class.sectionmanager.php');

/*
** The fabled event wrapper
*/

abstract class EventEx extends Event 
{
	/*
	** Constants 
	*/
	
	// resultant XML nominclature
	const ATTRIBUTE_SECTION_ID 		= "section-id";
	const ATTRIBUTE_SECTION_HANDLE 	= "section-handle";
	const ATTRIBUTE_INDEX_KEY		= "index-key";
	
	// types of XML objects
	const XML_TYPE_SYMPHONY			= 0;
	const XML_TYPE_SIMPLEXML		= 1;
	const XML_TYPE_TEXT				= 2;
	
	// String matching
	const REGEX_PLACEHOLDER			= '/([a-z-]+)\[(([^\[:]+)(?::([^\[]*))*)\]/';

	/*
	** Members 
	*/
	
	// Element node name
	protected	 $ROOT_ELEMENT;
	
	/*
	** Methods 
	*/
		
	public static function allowEditorToParse(){
		return false;
	}

	public static function documentation(){
		return null;
	}
	
	function __construct(&$parent, $env=NULL)
	{
		$szRootElement = get_class($this);
		$this->ROOT_ELEMENT = Lang::createHandle(General::right($szRootElement, strlen($szRootElement)-5));
		
		parent::__construct($parent, $env);
	}
	
	// Get an interface for a section's postdata
	protected function getSectionPostHandle( $section )
	{
		if (!in_array($section,$this->getSource()))
			throw new Exception(get_class($this) . ":: ". __FUNCTION__ . " attempted; section not in event scope.");
			
		return new PostModifier($section);
	}
	
	// Throw an error
	protected function throwError( $message, $nodename = null )
	{
		if ($nodename)
			$message = "<{$nodename}>{$message}</{$nodename}>";
			
		return  "<{$this->ROOT_ELEMENT}><entry result='error'>{$message}</entry></{$this->ROOT_ELEMENT}>";
	}
	
	// Get an a SimpleXML container for the event return
	protected function getXMLContainer( $type = EventEx::XML_TYPE_SYMPHONY )
	{
		$ret = null;
		
		switch ($type)
		{
			case EventEx::XML_TYPE_SYMPHONY:
				$ret = new XMLElement($this->ROOT_ELEMENT);
				break;
				
			case EventEx::XML_TYPE_SIMPLEXML:
				$ret = new SimpleXMLElement("<{$this->ROOT_ELEMENT} />");
				break;
				
			case EventEx::XML_TYPE_TEXT:
				$ret = "<{$this->ROOT_ELEMENT} />";
				break;							
		}
		
		return $ret; 
	}
	
	// Override for custom trigger functions
	public function load()
	{						
		if(isset($_POST['action'][$this->ROOT_ELEMENT])) 
			return $this->__trigger();
	}
	
	// Wrapper for GenericSectionUpdater::updateNamedSections
	protected function updateNamedSections( $aSections )
	{
		$this->setupDatabaseManipulator();
		return GenericSectionUpdater::updateNamedSections($this->ROOT_ELEMENT, $this->_Parent, $aSections);
	}
	
	// initilaize the DB manipulator object
	protected function setupDatabaseManipulator()
	{
		require_once(EXTENSIONS . '/databasemanipulator/lib/class.databasemanipulator.php');
		DatabaseManipulator::associateParent($this->_Parent);
	}		
	
	// Lazy access to Env keys
	protected function getEnvKey($container, $value)
	{
		return $this->_env['env'][$container][$value];
	}
	
	// Instantiate Ex helper objects
	protected function objectFactory()
	{
		// Retrieve arguments list
	    $args = func_get_args();
	    
	    // Delete the first argument which is the class name
	    $class_name = array_shift($args);
	    
	    // Prepend the _parent
	    array_unshift($args, $this->_Parent);
		
	    // Create Reflection object
	    $reflection = new ReflectionClass($class_name);
	
	    // Use the Reflection API
	    return $reflection->newInstanceArgs($args);
	}
}

/*
** Modify provided post results
*/

class PostModifier
{
	private		$_key;
	
	public function __construct( $fieldkey=null )
	{
		$this->setFieldKey($fieldkey);
	}
	
	public function setFieldKey( $fieldkey=null )
	{
		if ($fieldkey) $this->_key = $fieldkey;
	}
	
	public function __set( $key, $value )
	{
		if (!$this->_key || !isset($_POST[$this->_key]))
			throw new Exception(__CLASS__.":: __set attempted with no array key set." );
			
		return $_POST[$this->_key][$key] = $value;
	}
	
	public function __get( $key )
	{
		if (!$this->_key || !isset($_POST[$this->_key]))
			throw new Exception(__CLASS__.":: __get attempted with no array key set." );
			
		return $_POST[$this->_key][$key];			
	}
	
	public function fetchData()
	{
		if (!$this->_key || !isset($_POST[$this->_key]))
			return null;
			
		return $_POST[$this->_key];
	}
}

/*
 * Update any section
 */
 
class GenericSectionUpdater
{
	private static $_current_section_evaluations = null;
	 
	public static function updateNamedSections( $szRootElement, $oParent, $aSectionArray  )
	{				
		// Store the current sections
		self::$_current_section_evaluations = $aSectionArray;
		
		$compiled_result = new XMLElement($szRootElement);			
		$oSectionUpdater = new GenericSectionUpdate($oParent);
		
		$oSectionUpdater->storeRedirect();
		
		$sm = new SectionManager($oParent);	
		
		$redirect = true;
	
		foreach ($aSectionArray as $entry)
		{
			/*
			** Build an array of field meta-data
			*/
			
			$field_array = array();
			
			$fields = end($sm->fetch($sm->fetchIDFromHandle($entry)));
			
			foreach ($fields->fetch() as $field)
			{
				$tmp = $field->get();				
				$field_array[strtolower($tmp['label'])] = $tmp;
			}
			
			/*
			** Run the update and return the Symphony XML
			*/
			
			$section_arrays = $oSectionUpdater->updateSections($sm->fetchIDFromHandle($entry), $entry, $field_array);
			
			foreach($section_arrays as $section)
			{				
				$compiled_result->appendChild($section);
				
				if ($section->getAttribute("result") == "error")
					$redirect = false;
			}	
		}
		
		// Fix up the SBLs
		$oSectionUpdater->resolveLinks();
		
		// Do the rollback
		$oSectionUpdater->rollbackTransaction();
		
		// redirect if set up
		if ($redirect) $oSectionUpdater->actionRedirect();
		
		return $compiled_result;
	}
	
	public static function isSectionBeingEvaluated( $szSectionName )
	{
		return in_array( $szSectionName, self::$_current_section_evaluations);
	}
}

Class GenericSectionUpdate extends Event
{
	// This stores the section ID
	private static $iSectionID=null;
	
	// Keep a record of all updated entries by section handle
	private $updated_entries = array();
	private $updated_counts  = array();
	
	// A record of created (deleteable) entries
	private $created_entries = array();
	
	// Second pass associations
	private $unresolved_links = array();
	private static $temporary_unreolved_links = null;
	
	// store the redirect string
	private $redirect_url = null;
	
	// node name; Symphony standard
	const ROOTELEMENT = 'entry';
	
	// Unused filter array
	public $eParamFILTERS = array();
			
	/*
	** Unused methods required by interface
	*/
	
	public static function about(){ return null; }
	
	public static function allowEditorToParse(){ return false; }

	public static function documentation(){ return null; }
	
	public function load(){	return null; }
	
	protected function __trigger() { return null; }
	
	/*
	** Modified methods
	*/
		
	public static function getSource()
	{
		return self::$iSectionID;
	}
	
	/*
	** Returns boolean for numbered array keys
	*/
	
	public static function isArraySequential( $array )
	{
		$concatenated_keys = "";
		
		foreach ($array as $key => $value)
			$concatenated_keys .= $key;		
			
		return is_numeric($concatenated_keys);
	}
	
	public function updateSections( $iSectionID, $szPostKey, $field_array )
	{	
		if (!isset($_POST[$szPostKey])) return false;
		
		$results = array();
		
		// A numbered array is a multiple-entry submission
		if (self::isArraySequential($_POST[$szPostKey]))
		{
			foreach ($_POST[$szPostKey] as $id => $entry)
				array_push($results, $this->updateSection($iSectionID, $szPostKey, $entry, $_FILES[$id][$szPostKey], $field_array, $id));
		}
		else
			array_push($results, $this->updateSection($iSectionID, $szPostKey, $_POST[$szPostKey], $_FILES[$szPostKey], $field_array));
			
		return $results;
	}
	
	/*
	** Handle the internal value linking
	*/
	
	// try and insert a dynamic value
	private static function __substitutePostValue( $old_key, $new_key, $value )
	{
		unset($_POST['fields'][$old_key]);
		$_POST['fields'][$new_key] = $value;		
	}
	
	// For backwards-compatability; maintain double arrow syntax
	private function __handleLinks( $key, $iSectionID, $szPostKey, &$local_unresolved_links)
	{		
		$parts = explode("=>", $key);
						
		// FIXME: separate this, use for system:id below
		if (count($parts) > 1)
		{
			$new_key = trim($parts[0]);
			
			$target_handle = trim($parts[1]);
				
			// More than one target entry means many-to-many; only one is one-to-many
			$target_index = (count($this->updated_entries[$target_handle]) > 1 ? $this->updated_counts[$szPostKey] : 0);
											
			$value = $this->updated_entries[$target_handle][$target_index];						
				
			if ($value)
			{
				self::__substitutePostValue($key, $new_key, $value);
			}
			else
			{
				// add this to a list for the second pass
				$local_unresolved_links[] = array( 'section-id' => $iSectionID, 
                                                    'target-handle' => $target_handle, 
                                                    'target-index' => $target_index,
                                                    'field-name' => $new_key 
                                                 );
			}	
			
			// Make sure nothing else happens
			return;
		}					
	}
	
	// Replace section[field] placeholders with their proper values 
	private function __processPlaceholders( $key, $iSectionID, $szPostKey, &$local_unresolved_links)
	{
		// reset the temporary container
		self::$temporary_unreolved_links = array();
		
		// Disgiuse escaped hyphens
		$_POST['fields'][$key] = preg_replace('/\\\\-/', '{@hyphen@}', $_POST['fields'][$key]);
		
		/*
		** Run all the callbacks
		*/
		
		// If pcre.backtrack_limit is exceeded, preg_replace_callback returns NULL
		if ($new_value = preg_replace_callback(EventEx::REGEX_PLACEHOLDER, array($this, 'placeholderCallback'), $_POST['fields'][$key]))
			$_POST['fields'][$key] = $new_value;	
					
		// Un-disguise escaped hyphens
		$_POST['fields'][$key] = preg_replace('/{@hyphen@}/', '-', $_POST['fields'][$key]);				
		
		// Set up any unresolved links
		foreach (self::$temporary_unreolved_links as $link)
		{			
			// Update the permanent placeholder
			$_POST[$szPostKey][$key] = $_POST['fields'][$key];		
			
			// Determine the target index
			$target_index = (count($this->updated_entries[$link['target-handle']]) > 1 ? $this->updated_counts[$szPostKey] : 0);
			
			// Add to the section-level list of missed links
			$local_unresolved_links[] = array( 'section-id' => $iSectionID, 
                                               'target-handle' => $link['target-handle'], 
                                               'target-index' => $target_index,
                                               'field-name' => $link['field-name'], 
                                               'replacement-key' => $link['replacement-key'],
                                               'this-postkey' => $szPostKey,
                                               'this-key' => $key
                                             );
		}
	}
	
	// Callback for the replacement above
	public function placeholderCallback( $matches )
	{		
		// Only sections defined in the event can be referenced
		if (!GenericSectionUpdater::isSectionBeingEvaluated($matches[1]))
			return $matches[0];
			
		// System:id links
		if ($matches[2] == 'system:id')
		{
			if (isset( $_POST[$matches[1]]['system:id']))
				return $_POST[$matches[1]]['system:id'];
			
			$replacement_key = '{@system-id:'.$matches[1].'@}';
			
			self::$temporary_unreolved_links[] =  array( 
                                                    'target-handle' => $matches[1],
                                                    'field-name' => $matches[2],
                                                    'replacement-key' => $replacement_key                                                                                                       
                                                 );
			
			return $replacement_key;
		}
	
		return $_POST[$matches[1]][$matches[2]];	
	}
	
	/*
	** Master update method 
	*/
	
	public function updateSection( $iSectionID, $szPostKey, $aEntry, $aFiles, $field_array, $index_key = null )
	{
		// Fake the getSource() method
		self::$iSectionID = $iSectionID;
		
		// create the list of updates
		if (!isset($this->updated_entries[$szPostKey]))
		{
			$this->updated_entries[$szPostKey] = array();
			$this->updated_counts[$szPostKey] = 0;
		}
		
		// Stage unresolvable links locally
		$local_unresolved_links = array();
		
		// Backup any POST fields
		$post_backup = $_POST['fields'];
		$id_backup = $_POST['id'];
		unset($_POST['id']);		
		
		// And _FILES data
		$files_backup = $_FILES['fields'];
		
		// Make sure only newly created entries are deleteable
		$is_deletable = true;
		
		// Entry ID, is present
		if ($aEntry["system:id"])
		{
			$_POST["id"] = $aEntry["system:id"];
			
			$is_deletable = false;
		} 
		
		// Spoof post and file fields
		$_POST['fields'] = $aEntry;
		$_FILES['fields'] = $aFiles;	
		
		// Ensure the system:id is removed from fields[]
		unset($_POST['fields']['system:id']);
				
		// Field-specific functionality
		foreach ($_POST['fields'] as $key => $field)
		{
			/*
			** Concatenate split fields
			*/
						
			if (is_array($field) && !self::isArraySequential($field))
			{			
				$separationChar = " ";
				
				if ($field_array[$key]['type'] == "date")
					$separationChar = '-';
				else if ($field_array[$key]['type'] == "taglist")
					$separationChar = ',';				
				
				$_POST['fields'][$key] = implode($separationChar, $field);
				
				// Remove any traing seperators
				$_POST['fields'][$key] = preg_replace('/'.$separationChar.'$/','',$_POST['fields'][$key]);
			}
					
			// Deal with linked fields (now deprecated)	
			$this->__handleLinks( $key, $iSectionID, $szPostKey, $local_unresolved_links );		
			
			// Parse the form values
			$this->__processPlaceholders( $key, $iSectionID, $szPostKey, $local_unresolved_links); 	
		}
		
		include(TOOLKIT . '/events/event.section.php');	 
		
		// Assign the 'fields' POST var			
		$_POST['fields'] = $post_backup;
		$_FILES['fields'] = $files_backup;		
		$_POST['id'] = $id_backup;			
	
		
		// Set the result attributes
		$result->setAttribute( EventEx::ATTRIBUTE_SECTION_ID , $iSectionID);
		$result->setAttribute( EventEx::ATTRIBUTE_SECTION_HANDLE, $szPostKey);
		
		if ($index_key)
			$result->setAttribute(EventEx::ATTRIBUTE_INDEX_KEY, $index_key);
		
		if ($result->getAttribute("result", $szPostKey) == "error")
		{		
			$this->rollback = true;			
			return $result;
		}
		
		// The update has succeeded.  Add in the post values
		// In the lurid Symphony tradition, $fields is defined elsewhere
		
		if(is_array($fields) && !empty($fields))
		{
			$post_values = new XMLElement('post-values');
			
			foreach($fields as $element_name => $value)
			{
				if(strlen($value) == 0) continue;
				
				$post_values->appendChild(new XMLElement($element_name, General::sanitize($value)));
			}
			
			$result->appendChild($post_values);
		}				
		
		// maintain the updates list
		$this->updated_entries[$szPostKey][] = $result->getAttribute("id");
		$this->updated_counts[$szPostKey]++;
		
		// Deleteables list
		if ($is_deletable)
			$this->created_entries[] = $result->getAttribute("id");
		
		// maintain the unresolved links list
		foreach ($local_unresolved_links as $link)
		{
			$link['entry-id'] = $result->getAttribute('id');
			$this->unresolved_links[] = $link; 		
		}
		
		// store the system:id if it's not already
		if (!$_POST[$szPostKey]['system:id'])
			$_POST[$szPostKey]['system:id'] = $result->getAttribute('id');
		
		return $result;
	}	
	
	public function rollbackTransaction()
	{
		// Don't delete if there are no errors
		if (!$this->rollback) return;		
			
		DatabaseManipulator::deleteEntries($this->created_entries);
	} 
	
	public function resolveLinks()
	{	
		// Don't both if we're rolling back
		if ($this->rollback) return;
		
		// Don't need this cluttering up the formspace	
		unset($_POST['fields']);
		unset($_POST['id']);
		
		foreach ($this->unresolved_links as $link)
		{
			// Fake the getSource() method
			self::$iSectionID = $link['section-id'];
			
			// Refresh the form submission			
			$_POST['fields'] = array();
			
			// Set up the ID
			$_POST['id'] = $link['entry-id'];
			
			// And pop in the field data	
			$value = $this->updated_entries[$link['target-handle']][$link['target-index']];
			
			if ($link['replacement-key'])
			{
				// replace the provided key with the system id
				$_POST['fields'][$link['this-key']] = preg_replace('/' . $link['replacement-key'] . '/', $value, $_POST[$link['this-postkey']][$link['this-key']]);
			}
			else
			{
				$_POST['fields'][$link['field-name']] = $value;	
			}	
			
			if ($value)
				include(TOOLKIT . '/events/event.section.php');	 
		}		
	}

	public function redirectCallback( $matches )
	{
		if (!isset($_POST[$matches[1]]))
			return $matches[0];
			
		// system:id is a special case 
		if ($matches[2] == 'system:id')
			return $_POST[$matches[1]]['system:id'];
			
		$value = Lang::createHandle($_POST[$matches[1]][$matches[3]]);
				
		return $value;
	}
	
	public function storeRedirect()
	{		
		if ($_POST['parse-redirect'])
		{		
			$this->redirect_url = $_POST['parse-redirect'];
			unset($_POST['parse-redirect']);
		}
	}
	
	public function actionRedirect()
	{
		if ($this->redirect_url)
		{
			$this->redirect_url = preg_replace_callback(EventEx::REGEX_PLACEHOLDER, array($this, 'redirectCallback'), $this->redirect_url);
			header("location: " . $this->redirect_url);
			die;
		}
	} 	
}