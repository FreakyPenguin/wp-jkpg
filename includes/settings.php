<?php

function jkpg_settings_init() {
	register_setting( 'jkpg', 'jkpg_options',
    array(
      'sanitize_callback' => 'jkpg_settings_sanitize'
    ));

	add_settings_section(
		'jkpg_section_adobe',
                'Adobe Settings',
                'jkpg_section_adobe_text',
		'jkpg'
	);

	add_settings_field(
		'jkpg_setting_adobe_clientid',
                'Client ID',
		'jkpg_setting_adobe_clientid',
		'jkpg',
		'jkpg_section_adobe',
	);

	add_settings_field(
		'jkpg_setting_adobe_clientsecret',
                'Client Secret',
		'jkpg_setting_adobe_clientsecret',
		'jkpg',
		'jkpg_section_adobe',
	);

	add_settings_field(
		'jkpg_setting_adobe_token',
                'Token',
		'jkpg_setting_adobe_token',
		'jkpg',
		'jkpg_section_adobe',
	);


  add_settings_section(
		'jkpg_section_pictures',
                'Picture Settings',
                'jkpg_section_pictures_text',
		'jkpg'
	);

  add_settings_field(
		'jkpg_setting_pictures_sizes',
                'Sizes',
		'jkpg_setting_pictures_sizes',
		'jkpg',
		'jkpg_section_pictures',
	);
  add_settings_field(
		'jkpg_setting_pictures_wmfile',
                'Watermark File',
		'jkpg_setting_pictures_wmfile',
		'jkpg',
		'jkpg_section_pictures',
	);
  add_settings_field(
		'jkpg_setting_pictures_wmwidth',
                'Watermark Width (%)',
		'jkpg_setting_pictures_wmwidth',
		'jkpg',
		'jkpg_section_pictures',
	);
  add_settings_field(
		'jkpg_setting_pictures_wmheight',
                'Watermark Height (%)',
		'jkpg_setting_pictures_wmheight',
		'jkpg',
		'jkpg_section_pictures',
	);
  add_settings_field(
		'jkpg_setting_pictures_wmx',
                'Watermark X Pos (%)',
		'jkpg_setting_pictures_wmx',
		'jkpg',
		'jkpg_section_pictures',
	);
  add_settings_field(
		'jkpg_setting_pictures_wmy',
                'Watermark Y Pos (%)',
		'jkpg_setting_pictures_wmy',
		'jkpg',
		'jkpg_section_pictures',
	);

  // Not pretty, but oauth sets the WP reserved "error" query parameter. This
  // causes WP to redirect and remove the parameter before displaying the page.
  // so here we save the error before it's too late
  if (is_admin() && isset($_GET['page']) && $_GET['page'] == 'jkpg_settings' &&
      isset($_GET['adobeauth']) && isset($_GET['error'])) {
    add_settings_error('jkpg_setting_adobe_token', 'jkpg_msg_adobeauth',
      "Adobe authentication unsuccessful: " . $_GET['error'], 'error');
    set_transient('jkpg_settings_errors',
      get_settings_errors('jkpg_setting_adobe_token'), 30);
    remove_query_arg('adobeauth', false);
  }
}
add_action( 'admin_init', 'jkpg_settings_init' );

function jkpg_settings_sanitize($data) {
  if (isset($data['jkpg_setting_adobe_token_clear']) &&
    $data['jkpg_setting_adobe_token_clear'] == 'clear') {
    $data['jkpg_setting_adobe_token'] = '';
    unset($data['jkpg_setting_adobe_token_clear']);
  }

  return $data;
}

function jkpg_options_page_html() {
    $options = get_option( 'jkpg_options' );
    if (isset($_GET['adobeauth'])) {
      if (isset($_GET['code'])) {
        add_settings_error('jkpg_setting_adobe_token', 'jkpg_msg_adobeauth_success',
          "Adobe authentication successful", 'success');
        try {
          $res = jkpg_adobe_request_token(
            $options['jkpg_setting_adobe_clientid'],
            $options['jkpg_setting_adobe_clientsecret'],
            $_GET['code']);
          $options['jkpg_setting_adobe_token'] = $res['token'];
          if ($res['expires_in'] > 0) {
            $options['jkpg_setting_adobe_tokenvalid'] =
              time() + $res['expires_in'];
          } else {
            $options['jkpg_setting_adobe_tokenvalid'] = 0;
          }
          
        } catch (Exception $e) {
          add_settings_error('jkpg_setting_adobe_token', 'jkpg_msg_token_fail',
              "Requesting adobe token failed: " . $e->getMessage(), 'error');
        }
      }
      update_option('jkpg_options', $options);
      $options = get_option( 'jkpg_options' );
      remove_query_arg(array('adobeauth', 'code'), false);
    }

    ?>
    <div class="wrap">
      <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <?php

    // Show any errors
    settings_errors();
    $form_errors = get_transient("jkpg_settings_errors");
    delete_transient("jkpg_settings_errors");

    ?>
      <form action="options.php" method="post">
        <?php
        // output security fields for the registered setting "jkpg_options"
        settings_fields( 'jkpg' );
        // output setting sections and their fields
        // (sections are registered for "jkpg", each field is registered to a specific section)
        do_settings_sections( 'jkpg' );
        // output save settings button
        submit_button( __( 'Save Settings', 'textdomain' ) );
        ?>
      </form>
    </div>
    <?php
}

function jkpg_section_adobe_text() {
  echo '<p>Settings and state for Adobe API</p>';
}

function jkpg_setting_adobe_clientid() {
  $options = get_option( 'jkpg_options' );
  echo "<input id='jkpg_setting_adobe_clientid' name='jkpg_options[jkpg_setting_adobe_clientid]' type='text' value='" . esc_attr( $options['jkpg_setting_adobe_clientid'] ) . "' />";
}

function jkpg_setting_adobe_clientsecret() {
$options = get_option( 'jkpg_options' );
echo "<input id='jkpg_setting_adobe_clientsecret' name='jkpg_options[jkpg_setting_adobe_clientsecret]' type='text' value='" . esc_attr( $options['jkpg_setting_adobe_clientsecret'] ) . "' />";
}

function jkpg_setting_adobe_token() {
  $options = get_option( 'jkpg_options' );
  $redir_url = add_query_arg( 'adobeauth', '1', menu_page_url( 'jkpg_settings', false ) );
  ?>
  <p>Current: <pre><?php echo esc_html( $options['jkpg_setting_adobe_token'] ) ?></pre></p>
  <p>
  <?php
  if (empty($options['jkpg_setting_adobe_tokenvalid']) ||
      $options['jkpg_setting_adobe_tokenvalid'] < time()) {
    echo "Invalid";
  } else if ($options['jkpg_setting_adobe_tokenvalid'] == 0) {
    echo "Valid Indefinitely";
  } else {
    $dt =  date("Y-m-d H:i:s", $options['jkpg_setting_adobe_tokenvalid']);
    echo "Valid Until: $dt";
  }
  ?>
  </p>
  <p><input id='jkpg_setting_adobe_token_clear' name='jkpg_options[jkpg_setting_adobe_token_clear]' type='checkbox' value='clear' />
  <label for="jkpg_setting_adobe_token_clear">Clear</label></p>
  <input id='jkpg_setting_adobe_token' name='jkpg_options[jkpg_setting_adobe_token]' type='hidden' value='<?php echo esc_attr( $options['jkpg_setting_adobe_token'] ) ?>' />
  <?php

  if (isset($options['jkpg_setting_adobe_clientid']) &&
      isset($options['jkpg_setting_adobe_clientsecret']) &&
      !empty($options['jkpg_setting_adobe_clientid']) &&
      !empty($options['jkpg_setting_adobe_clientsecret'])) {
    ?>
    <p><a href='<?php echo jkpg_adobe_auth_url($options['jkpg_setting_adobe_clientid'], $redir_url); ?>'>Refresh</a></p>
    <?php
  }
}


function jkpg_section_pictures_text() {
  echo '<p>Settings for pictures</p>';
}

function jkpg_setting_pictures_sizes() {
  $options = get_option( 'jkpg_options' );
  echo "<input id='jkpg_setting_pictures_sizes' name='jkpg_options[jkpg_setting_pictures_sizes]' type='text' value='" . esc_attr( $options['jkpg_setting_pictures_sizes'] ) . "' />";
}

function jkpg_setting_pictures_wmfile() {
  $options = get_option( 'jkpg_options' );
  echo "<input id='jkpg_setting_pictures_wmfile' name='jkpg_options[jkpg_setting_pictures_wmfile]' type='text' value='" . esc_attr( $options['jkpg_setting_pictures_wmfile'] ) . "' />";
}

function jkpg_setting_pictures_wmwidth() {
  $options = get_option( 'jkpg_options' );
  echo "<input id='jkpg_setting_pictures_wmwidth' name='jkpg_options[jkpg_setting_pictures_wmwidth]' type='text' value='" . esc_attr( $options['jkpg_setting_pictures_wmwidth'] ) . "' />";
}

function jkpg_setting_pictures_wmheight() {
  $options = get_option( 'jkpg_options' );
  echo "<input id='jkpg_setting_pictures_wmheight' name='jkpg_options[jkpg_setting_pictures_wmheight]' type='text' value='" . esc_attr( $options['jkpg_setting_pictures_wmheight'] ) . "' />";
}

function jkpg_setting_pictures_wmx() {
  $options = get_option( 'jkpg_options' );
  echo "<input id='jkpg_setting_pictures_wmx' name='jkpg_options[jkpg_setting_pictures_wmx]' type='text' value='" . esc_attr( $options['jkpg_setting_pictures_wmx'] ) . "' />";
}

function jkpg_setting_pictures_wmy() {
  $options = get_option( 'jkpg_options' );
  echo "<input id='jkpg_setting_pictures_wmy' name='jkpg_options[jkpg_setting_pictures_wmy]' type='text' value='" . esc_attr( $options['jkpg_setting_pictures_wmy'] ) . "' />";
}