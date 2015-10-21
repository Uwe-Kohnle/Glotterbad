<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
* XML writer class
*
* Class to simplify manual writing of xml documents.
* It only supports writing xml sequentially, because the xml document
* is saved in a string with no additional structure information.
* The author is responsible for well-formedness and validity
* of the xml document.
*
* @author Matthias Rulinski <matthias.rulinski@mi.uni-koeln.de>
* @version $Id$
*/
class ilXmlWriter
{
	/**
	* string containing xml document
	* @var		string
	* @access	private
	*/
	var $xmlStr;

	/**
	* xml version
	* @var		string
	* @access	private
	*/
	var $version;

	/**
	* output encoding
	* @var		string
	* @access	private
	*/
	var $outEnc;

	/**
	* input encoding
	* @var		string
	* @access	private
	*/
	var $inEnc;

	/**
	* dtd definition
	* @var		string
	* @access	private
	*/
	var $dtdDef = "";

	/**
	* stylesheet
	* @var		string
	* @access	private
	*/
	var $stSheet = "";

	/**
	* generated comment
	* @var		string
	* @access	private
	*/
	var $genCmt = "Generated by ILIAS XmlWriter";

	/**
	* constructor
	* @param	string	xml version
	* @param	string	output encoding
	* @param	string	input encoding
	* @access	public
	*/
	function ilXmlWriter ($version = "1.0", $outEnc = "utf-8", $inEnc = "utf-8")
	{
		// initialize xml string
		$this->xmlStr = "";
		
		// set properties
		$this->version = $version;
		$this->outEnc = $outEnc;
		$this->inEnc = $inEnc;
	}
	
	/**
	* destructor 
	* @access	public
	*/
	function _ilXmlWriter ()
	{
		// terminate xml string
		unset($this->xmlStr);
	}
	
	/**
	* Sets dtd definition
	* @param	string	dtd definition
	* @access	public
	*/
	function xmlSetDtdDef ($dtdDef)
	{
		$this->dtdDef = $dtdDef;
	}
	
	/**
	* Sets stylesheet
	* @param	string	stylesheet
	* @access	public
	*/
	function xmlSetStSheet ($stSheet)
	{
		$this->stSheet = $stSheet;
	}
	
	/**
	* Sets generated comment
	* @param	string	generated comment
	* @access	public
	*/
	function xmlSetGenCmt ($genCmt)
	{
		$this->genCmt = $genCmt;
	}

	/**
	* Escapes reserved characters
	* @param	string	input text
	* @return	string	escaped text
	* @access	static
	*/
	function _xmlEscapeData($data)
	{
		$position = 0;
		$length = strlen($data);
		$escapedData = "";
		
		for(; $position < $length;)
		{
			$character = substr($data, $position, 1);
			$code = Ord($character);
			
			switch($code)
			{
				case 34:
					$character = "&quot;";
					break;
				
				case 38:
					$character = "&amp;";
					break;
				
				case 39:
					$character = "&apos;";
					break;
				
				case 60:
					$character = "&lt;";
					break;
				
				case 62:
					$character = "&gt;";
					break;
				
				default:
					if ($code < 32)
					{
						$character = ("&#".strval($code).";");
					}
					break;
			}
			
			$escapedData .= $character;
			$position ++;
		}
		return $escapedData;
	}
	
	/**
	* Encodes text from input encoding into output encoding
	* @param	string	input text
	* @return	string	encoded text
	* @access	private
	*/
	function xmlEncodeData($data)
	{
		if ($this->inEnc == $this->outEnc)
		{
			$encodedData = $data;
		}
		else
		{
			switch(strtolower($this->outEnc))
			{
				case "utf-8":
					if(strtolower($this->inEnc) == "iso-8859-1")
					{
						$encodedData = utf8_encode($data);
					}
					else
					{
						die ("<b>Error</b>: Cannot encode iso-8859-1 data in ".$this->outEnc.
								" in <b>".__FILE__."</b> on line <b>".__LINE__."</b><br />");
					}
					break;
				
				case "iso-8859-1":
					if(strtolower($this->inEnc) == "utf-8")
					{
						$encodedData = utf8_decode($data);
					}
					else
					{
						die ("<b>Error</b>: Cannot encode utf-8 data in ".$this->outEnc.
								" in <b>".__FILE__."</b> on line <b>".__LINE__."</b><br />");
					}
					break;
					
				default:
					die ("<b>Error</b>: Cannot encode ".$this->inEnc." data in ".$this->outEnc.
							" in <b>".__FILE__."</b> on line <b>".__LINE__."</b><br />");
			}
		}
		return $encodedData;
	}
	
	/**
	* Indents text for better reading
	* @param	string	input text
	* @return	string	indented text
	* @access	private
	*/
	function xmlFormatData($data)
	{
		// regular expression for tags
		$formatedXml = preg_replace_callback("|<[^>]*>[^<]*|", array($this, "xmlFormatElement"), $data);
		
		return $formatedXml;
	}
	
	/**
	* Callback function for xmlFormatData; do not invoke directly
	* @param	array	result of reg. expr. search
	* @return	string	indented substring
	* @access	private
	*/
	function xmlFormatElement($array)
	{
		$found = trim($array[0]);
		
		static $indent;
		
		// linebreak (default)
		$nl = "\n";
		
		$tab = str_repeat(" ", $indent * 2);
		
		// closing tag
		if (substr($found, 0, 2) == "</")
		{
			if($indent)
			{
				$indent --;
			}
			$tab = str_repeat(" ", $indent * 2);
		}
		elseif (substr($found, -2, 1) == "/" or // opening and closing, comment, ...
				strpos($found, "/>") or
				substr($found, 0, 2) == "<!") 
		{
			// do not change indent
		}
		elseif (substr($found, 0, 2) == "<?") 
		{
			// do not change indent
			// no linebreak
			$nl = "";
		}
		else // opening tag
		{
			$indent ++;
		}
		
		// content
		if (substr($found, -1) != ">")
		{
			$found = str_replace(">", ">\n".str_repeat(" ", ($indent + 0) * 2), $found);
		}
		
		return $nl.$tab.$found;
	}
	
	/**
	* Writes xml header
	* @access	public
	*/
	function xmlHeader()
	{
		// version and encoding
		$this->xmlStr .= "<?xml version=\"".$this->version."\" encoding=\"".$this->outEnc."\"?>";
		
		// dtd definition
		if ($this->dtdDef <> "")
		{
			$this->xmlStr .= $this->dtdDef;
		}
		
		// stSheet
		if ($this->stSheet <> "")
		{
			$this->xmlStr .= $this->stSheet;
		}
		
		// generated comment
		if ($this->genCmt <> "")
		{
			$this->xmlComment($this->genCmt);
		}
		
		return $xmlStr;
	}
	
	/**
	* Writes a starttag
	* @param	string	tag name
	* @param	array	attributes (name => value)
	* @param	boolean	tag empty (TRUE) or not (FALSE)
	* @param	boolean	ecode attributes' values (TRUE) or not (FALSE)
	* @param	boolean	escape attributes' values (TRUE) or not (FALSE)
	* @access	public
	*/
	function xmlStartTag ($tag, $attrs = NULL, $empty = FALSE, $encode = TRUE, $escape = TRUE)
	{
		// write first part of the starttag
		$this->xmlStr .= "<".$tag;
		
		// check for existing attributes
		if (is_array($attrs))
		{
			// write attributes
			foreach ($attrs as $name => $value)
			{
				// encode
				if ($encode)
				{
		    		$value = $this->xmlEncodeData($value);
				}
				
				// escape
				if ($escape)
				{
					$value = ilXmlWriter::_xmlEscapeData($value);
	    		}
				
				$this->xmlStr .= " ".$name."=\"".$value."\"";
			}
		}
		
		// write last part of the starttag
		if ($empty)
		{
			$this->xmlStr .= "/>";
		}
		else
		{
			$this->xmlStr .= ">";
		}
	}
	
	/**
	* Writes an endtag
	* @param	string	tag name
	* @access	public
	*/
	function xmlEndTag ($tag)
	{
		$this->xmlStr .= "</".$tag.">";
	}
	
	/**
	* Writes a comment
	* @param	string	comment
	* @access	public
	*/
	function xmlComment ($comment)
	{
		$this->xmlStr .= "<!--".$comment."-->";
	}

	/**
	* Writes data
	* @param	string	data
	* @param	string	ecode data (TRUE) or not (FALSE)
	* @param	string	escape data (TRUE) or not (FALSE)
	* @access	public
	*/
	function xmlData ($data, $encode = TRUE, $escape = TRUE)
	{
		// encode
		if ($encode)
		{
		    $data = $this->xmlEncodeData($data);
		}
		
		// escape
		if ($escape)
		{
			$data = ilXmlWriter::_xmlEscapeData($data);
	    }
		
		$this->xmlStr .= $data;
	}
	
	/**
	* Writes a basic element (no children, just textual content)
	* @param	string	tag name
	* @param	array	attributes (name => value)
	* @param	string	data
	* @param	boolean	ecode attributes' values and data (TRUE) or not (FALSE)
	* @param	boolean	escape attributes' values and data (TRUE) or not (FALSE)
	* @access	public
	*/
	function xmlElement ($tag, $attrs = NULL, $data = Null, $encode = TRUE, $escape = TRUE)
	{
		// check for existing data (element's content)
		if (is_string($data) or
			is_integer($data) or
			is_float($data))
		{
			// write starttag
			$this->xmlStartTag($tag, $attrs, FALSE, $encode, $escape);
			
			// write text
			$this->xmlData($data, $encode, $escape);
			
			// write endtag
			$this->xmlEndTag($tag);
		}
		else // no data
		{
			// write starttag (= empty tag)
			$this->xmlStartTag($tag, $attrs, TRUE, $encode, $escape);
		}
	}
	
	/**
	* Dumps xml document from memory into a file
	* @param	string	file name (full path)
	* @param	boolean	indent text (TRUE) or not (FALSE)
	* @access	public
	*/
	function xmlDumpFile($file, $format = TRUE)
	{
		// open file
		if (!($fp = @fopen($file,"w+")))
		{
			die ("<b>Error</b>: Could not open \"".$file."\" for writing".
					" in <b>".__FILE__."</b> on line <b>".__LINE__."</b><br />");
		}
		
		// set file permissions
		chmod($file, 0770);
		
		// format xml data
		if ($format)
		{
			$xmlStr = $this->xmlFormatData($this->xmlStr);
		}
		else
		{
			$xmlStr = $this->xmlStr;
		}
		
		// write xml data into the file
		fwrite($fp, $xmlStr);
		
		// close file
		fclose($fp);
	}
	
	/**
	* Returns xml document from memory
	* @param	boolean	indent text (TRUE) or not (FALSE)
	* @return	string	xml document
	* @access	public
	*/
	function xmlDumpMem($format = TRUE)
	{
		// format xml data
		if ($format)
		{
			$xmlStr = $this->xmlFormatData($this->xmlStr);
		}
		else
		{
			$xmlStr = $this->xmlStr;
		}
		
		return $xmlStr;
	}

	/**
	* append xml string to document
	*/
	function appendXML($a_str)
	{
		$this->xmlStr .= $a_str;
	}
	
	/**
	* clears xmlStr
	* @access	public
	*/
	function xmlClear ()
	{
		// reset xml string
		$this->xmlStr = "";
	}

}

?>
