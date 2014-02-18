<?php
/*
Plugin Name: Child Themes
Plugin URI: http://www.stillbreathing.co.uk/wordpress/child-themes/
Description: Makes creating child themes really easy
Version: 1.0
Author: Chris Taylor
Author URI: http://www.stillbreathing.co.uk
*/

class ChildThemes {

	// create a list of the default headers for the different properties of a theme
	// from wp-includes/theme.php get_theme_data()
	var $default_headers = array(
		'Name' => 'Theme Name',
		'URI' => 'Theme URI',
		'Description' => 'Description',
		'Author' => 'Author',
		'AuthorURI' => 'Author URI',
		'Version' => 'Version',
		'Template' => 'Template',
		'Status' => 'Status',
		'Tags' => 'Tags'
	);

	// start the plugin, adding the admin menu item
	function Start() {
		if ( function_exists( "add_action" ) ) {
			add_action( "admin_menu", array( $this, 'AddAdminMenu' ) );
		}
		if ( function_exists( "add_filter" ) ) {
			add_filter( 'theme_action_links', array( $this, 'AddThemeActionFilter' ), 1000, 2 );
		}
	}

	// add the link to create the child theme to the action links filter
	function AddThemeActionFilter( $actions, $theme ) {
		$actions[] = '<a href="admin.php?page=childthemes&amp;theme=' . urlencode( $theme[ "Name" ] ) . '">' . __( "Create child theme", "childthemes" ) . '</a>';
		return $actions;
	}

	// add the admin menu link
	function AddAdminMenu() {
		add_submenu_page( 'themes.php', 
			__( "Child themes", "childthemes" ), 
			__( "Child themes", "childthemes" ), 
			"edit_themes", 
			'childthemes', 
			array( &$this, 'ShowAdminPage' ) 
		);
	}
	
	// show the admin page
	function ShowAdminPage() {
	
		// get all the available themes
		$themes = wp_get_themes();
		
		// debugging
		//print_r($themes);
	
		echo '
		<div class="wrap" id="childthemes">
		';
		
		// show the list of themes
		if ( !isset( $_GET[ "theme" ] ) && !isset( $_GET[ "childtheme" ] ) ) {
		
			$this->ShowThemeList( $themes );
			
		}
		
		// if a theme to create a child from has been chosen
		if ( isset( $_GET[ "theme" ] ) ) {
		
			// get the slug of the theme (this is the folder name)
			$theme_slug = urldecode( $_GET[ "theme" ] );
		
			// get the details for this theme
			$theme = $themes[ $theme_slug ];
			if ( $theme ) {
		
				// if the form has been submitted
				if ( is_array( $_POST ) && count( $_POST ) > 0 ) {
				
					$this->CreateChildTheme( $theme, $_POST );
				
				} else {
				
					// show the form to set the details of the child theme
					$this->ShowChildThemeForm( $theme_slug, $theme );
				
				}
				
			// oops, the theme can't be found
			} else {
			
				echo '
				<h2>' . __( "Theme not found", "childthemes" ) . '</h2>
				<p>' . __( "Sorry, the theme you've chosen cannot be found. Click back and try again.", "childthemes" ) . '</p>
				';
			
			}
		}
		
		echo '
		</div>
		';
	}
	
	// create a child theme from the submitted form data
	function CreateChildTheme( $theme, $post ) {

		echo '
		<h2>' . __( "Creating child theme...", "childthemes" ) . '</h2>
		';
	
		// get the basic details of the parent theme
		$theme_slug = $post[ "ThemeSlug" ];
		$folder = sanitize_title( $post[ "Name" ] );
		$root = get_theme_root();
		
		// check if the child theme folder we are creating already exists
		if ( is_dir( $root . '/' . $folder ) ) {
		
			$this->ShowError( sprintf( __( "The theme folder '%s' already exists. Choose a different name.", "childthemes" ), $folder ) );
			$this->ShowChildThemeForm( $theme_slug, $theme );
			return;
		
		}
			
		// check we can get the parent theme stylesheet
		$stylefile = $theme[ "Stylesheet Dir" ] . "/style.css";
		if ( !file_exists( $stylefile ) ) {
		
			$this->ShowError( sprintf( __( "The parent style.css file '%s' could not be found:", "childthemes" ), $stylefile ) );
			$this->ShowChildThemeForm( $theme_slug, $theme );
			return;
		
		}
		
		// make sure we can create the folder for the new child theme
		if ( !mkdir( $root . '/' . $folder ) ) {
			
			$this->ShowError( sprintf( __( "The theme folder '%s' could not be created. Perhaps permissions are wrong.", "childthemes" ), $folder ) );
			$this->ShowChildThemeForm( $theme_slug, $theme );
			return;
			
		}
		
		// get the contents of the parent theme stylesheet
		$style = file_get_contents( $stylefile );
		$len = strpos( $style, "*/" );
		$style = trim( substr( $style, $len + 2 ) );
		
		// create the new stylesheet with the child theme details
		$style = $this->CreateHeaders( $theme_slug, $theme, $post ) . $style;
	
		// check we can save the new child stylesheet
		$childstylesheet = $root . '/' . $folder . '/style.css';
		if ( !file_put_contents( $childstylesheet, $style ) ) {
		
			$this->ShowError( sprintf( __( "The new style.css file could not be created at '%s'. Perhaps permissions are wrong.", "childthemes" ), $childstylesheet ) );
			$this->ShowChildThemeForm( $theme_slug, $theme );
			return;
			
		}
	
		// if we get here we know the new child folder and stylesheet have been created
		echo '
		<div class="updated">
		<p>' . __( "Your child theme has been created.", "childthemes" ) . '</p>
		</div>
		';

		// try to copy the selected files
		$completedfiles = 0;
		$failedfiles = array();
		if ( isset( $post[ "filestocopy" ] ) ) {
		
			foreach( $post[ "filestocopy" ] as $file ) {
			
				$file = substr( $file, strrpos( $file, '/' ) + 1 );
				
				if ( !copy( $theme[ "Template Dir" ] . '/' . $file, $root . '/' . $folder . '/' . $file ) ) {
				
					$failedfiles[] = $file;
					
				} else {
				
					$completedfiles++;
					
				}
				
			}
		}
		
		// if any files could not be copied show the errors
		if ( count( $failedfiles ) > 0 ) {
		
			echo '
			<div class="error">
			';
			$this->ShowError( __( "The following files could not be copied:", "childthemes" ) );
			echo '
			<ul>
			';
			
			foreach( $failedfiles as $file ) {
				echo '
				<li>' . $file . '</li>
				';
			}
			
			echo '
			</ul>
			</div>
			';
			
		// all selected files (including style.css) could be copied, hurrah
		} else {
		
			echo '
			<div class="updated">
			<p>' . sprintf( __( "Files copied: %d", "childthemes" ), $completedfiles + 1 ) . '</p>
			</div>
			';
			
		}
		
		echo '
		<p>' . __( 'Would you like to <a href="themes.php?page=childthemes">create another child theme?</a>', 'childthemes' ) . '</p>
		';
	}
	
	// displays an error message
	function ShowError( $message ) {
	
		echo '
		<div class="error">
		<p>' . $message . '</p>
		</div>
		';
	
	}
	
	// show the list of themes to create a child from
	function ShowThemeList( $themes ) {
	
		// sort the themes alphabetically
		$theme_names = array_keys($themes);
		natcasesort($theme_names);
		
		echo '
		
			<h2>' . __( "Create child theme", "childthemes" ) . '</h2>
			<p>' . __( "Choose a theme from below to create a child theme.", "childthemes" ) . '</p>
			
			<div class="theme-browser">
				<div class="themes">
			';
		
			// loop each theme displaying the screenshot and link to create a child theme
			foreach( $theme_names as $theme_name ) {
			
				$title = $themes[$theme_name][ 'Title' ];
				$stylesheet = $themes[$theme_name][ 'Stylesheet' ];
				$screenshot = $themes[$theme_name]->get_screenshot();
				$theme_root_uri = $themes[$theme_name][ 'Theme Root URI' ];
				$template = $themes[$theme_name][ "Template" ];
				$description = $themes[$theme_name][ 'Description' ];
				$author = $themes[$theme_name][ 'Author' ];
				$version = $themes[$theme_name][ 'Version' ];
				
				echo '
				<div class="theme" tabindex="0">';
				
				// do we have a screenshot?
				if ( $screenshot != '' ) {
				
					echo '
					<div class="theme-screenshot">
						<img src="' . $screenshot . '" alt="" />
					</div>';
					
				// nope, no screenshot
				} else {
				
					echo '
					<div class="theme-screenshot blank"></div>';
					
				}
				
				echo '
					<h3 class="theme-name">' . $title . '</h3>
					<div class="theme-actions">
						<a class="button button-primary" href="themes.php?page=childthemes&amp;theme=' . urlencode( $theme_name ) . '">
					';
				echo __( "Create child theme", "childthemes" );
				echo '
						</a>
					</div>
				</div>';
			}
		
		echo '
				</div>
			</div>';
	
	}
	
	// displays the form to allow the user to set the details for the new theme
	function ShowChildThemeForm( $theme_slug, $theme ) {
		global $current_user;
		get_currentuserinfo();
		
		// debugging
		//print_r($theme);
		
		echo '
		<h2>' . $theme[ 'Title' ] . ': ' . __( "Create child theme", "childthemes" ) . '</h2>
		';
		
		// display the screenshot of the parent theme if we have one
		$screenshot = $theme->get_screenshot();
		if ( $screenshot != '' ) {
			echo '
			<p><img src="' . $screenshot . '" alt="" width="364px" /></p>
			';
		}
		
		echo '
		<p>' . __( "Theme author:", "childthemes" ) . ' <a href="' . $theme->AuthorURI . '">' . $theme->Author . '</a></p>
		<p>' . __( "Set the details for your child theme below.", "childthemes" ) . '</p>
		
		<form action="themes.php?page=childthemes&amp;theme=' . $theme_slug . '" method="post">
		<fieldset>
		<h3>' . __( "Child theme settings", "childthemes" ) . '</h3>';

		// loop all the headers to populate the form fields
		$value = "";
		$type = "text";
		foreach( $this->default_headers as $key => $val ) {

			// get the value of this field
			if ( array_key_exists( $key, $theme ) ) {
				$value = $theme[ $key ];
			}
			
			// if a form has been submitted try to get the value from the form values
			// this means if there's a problem the users values will be repopulated
			if ( count( $_POST ) > 0 ) {
				if ( array_key_exists( $key, $_POST ) ) {
					$value = $_POST[ $key ];
				}
			}
			
			// set the description field to be a textarea
			if ( $key == "Description" ) {
				$type = "textarea";
			}
			
			// if the tags are an array make them a CSV
			if ( $key == "Tags" ) {
				$value = "";
				if ( $value != "" && is_array( $value ) && count( $value ) ) {
					$value = implode( ",", $value );
				}
			}
			
			// set the version to 1.0
			if ( $key == "Version" ) {
				$value = "1.0";
			}
			
			// write out the form input, but not for the status or template fields
			if ( $key != "Status" && $key != "Template" ) {
				echo $this->WriteSettingInput( $type, $val, $key, $value );
			}
			
			$type = "text";
		
		}
		
		echo '
		</fieldset>
		';
	
		// allow the user to select which files they want to copy to the new child theme
		echo '
		<h3>' . __( "Files to copy", "childthemes" ) . '</h3>
		<p>' . __( "Select which files from the parent theme you wish to copy to your child theme. The <code>style.css</code> file will be copied automatically and edited with the details you enter.", "childthemes" ) . '</p>
		';
	
		// show the list of template files to copy to the child theme
		$template_mapping = array();
		$template_dir = $theme[ 'Template Dir' ];
		
		foreach ( $theme[ 'Template Files' ] as $template_file ) {
		
			$description = trim( get_file_description( $template_file ) );
			$template_show = basename( $template_file );
			$filedesc = ( $description != $template_file ) ? "$description<br /><span class='nonessential'>($template_show)</span>" : "$description";

			// If we have two files of the same name prefer the one in the Template Directory
			// This means that we display the correct files for child themes which overload Templates as well as Styles
			if ( array_key_exists($description, $template_mapping ) ) {
			
				if ( false !== strpos( $template_file, $template_dir ) )  {
				
					$template_mapping[ $description ] = array( _get_template_edit_filename( $template_file, $template_dir ), $filedesc );
					
				}
				
			} else {
			
				$template_mapping[ $description ] = array( _get_template_edit_filename( $template_file, $template_dir ), $filedesc );
				
			}
		}
		ksort( $template_mapping );
		echo '
		
		</fieldset>
		<h4>' . __( "Templates" ) . '</h4>
		<ul>
		';
		
		// display the templates
		while ( list( $template_sorted_key, list( $template_file, $filedesc ) ) = each( $template_mapping ) ) {
			echo '
			<li style="float:left;width:250px"><label><input type="checkbox" name="filestocopy[]" value="' . $template_file . '"> ' . $filedesc . '</label></li>
			';
		}
		
		echo '
		</ul>
		</fieldset>
		';
		
		echo '
		<fieldset style="clear:both;padding-top:20px;">
		<h4>' . __( "Stylesheets" ) . '</h4>
		<ul>
		';
		
		// show the list of stylesheet files to copy to the child theme
		$template_mapping = array();
		$stylesheet_dir = $theme[ 'Stylesheet Dir' ];
		
		foreach ( $theme[ 'Stylesheet Files' ] as $style_file ) {
		
			$style_show = basename( $style_file );
			
			if ( $style_show != "style.css" ) {
			
				$description = trim( get_file_description( $style_file ) );
				$filedesc = ( $description != $style_file ) ? "$description<br /><span class='nonessential'>($style_show)</span>" : "$description";
				$template_mapping[ $description ] = array( _get_template_edit_filename( $style_file, $stylesheet_dir ), $filedesc );
				
			}
		}
		ksort( $template_mapping );
		
		// display the stylesheet files
		while ( list( $template_sorted_key, list( $style_file, $filedesc ) ) = each( $template_mapping ) ) {
		
			echo '
			<li style="float:left;width:250px"><label><input type="checkbox" name="filestocopy[]" value="' . $style_file . '"> ' . $filedesc  . '</label></li>
			';
			
		}
		echo '
		</ul>
		</fieldset>
		
		<fieldset style="clear:left">
		<p>
			<input type="hidden" name="ThemeSlug" value="' . $theme_slug . '" />
			<button class="button-primary">' . __( "Create child theme", "childthemes" ) . '</button>
		</p>
		</fieldset>
		';
		
		echo '
		</form>
		';
	}
	
	// writes out a form label and input in a paragraph
	function WriteSettingInput( $type, $label, $name, $value ) {
	
		// single line text
		if ( $type == "text" ) {
		
			echo '
		<p>
			<label for="' . $name . '" style="clear:left;float:left;width:20%;">' . __( $label, "childthemes" ) . '</label>
			<input type="text" name="' . $name . '" id="' . $name . '" style="width:78%" value="' . $value . '" />
		</p>
			';
			
		// multi-line text
		} else if ( $type == "textarea" ) {
		
			echo '
		<p>
			<label for="' . $name . '" style="clear:left;float:left;width:20%;">' . __( $label, "childthemes" ) . '</label>
			<textarea name="' . $name . '" id="' . $name . '" rows="4" cols="30" style="width:78%">' . $value . '</textarea>
		</p>
			';
			
		}
	}
	
	// creates the headers in the style.css for the new child theme
	function CreateHeaders( $theme_slug, $theme, $post ) {
		
		$o = '/*
';
		
		// loop the headers and get the 
		foreach( $this->default_headers as $key => $value ) {
		
			// we skip the status and template values
			if ( $key != "Status" && $key != "Template" ) {
			
				$o .= $value . ": " . $post[ $key ] . '
';

			}

			// we write out the parent theme slug, this is what makes the new child theme a child of that parent
			if ( $key == "Template" ) {
			
				$o .= $value . ": " . $theme_slug . '
';

			}
		}
		
		$o .= '*/
';
		return $o;

	}
}

// create the child themes object and start it
$childthemes = new ChildThemes();
$childthemes->Start();
?>