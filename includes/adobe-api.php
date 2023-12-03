<?php

function jkpg_adobe_auth_url ($clientid, $redir_url) {
  return add_query_arg (
    array(
      'client_id' => $clientid,
      'redirect_uri' => rawurlencode($redir_url),
      'scope' => 'openid,lr_partner_apis,lr_partner_rendition_apis',
      'response_type' => 'code'
    ),
    'https://ims-na1.adobelogin.com/ims/authorize/v2'
  );
}

function jkpg_adobe_request_token( $clientid, $clientsecret, $code) {
  $url = 'https://ims-na1.adobelogin.com/ims/token/v3';
  $auth = base64_encode( $clientid . ':' . $clientsecret );
  /*  */
  $response = wp_remote_post( $url, array(
    'body'    => "client_id=$clientid&client_secret=$clientsecret&code=".rawurlencode($code)."&grant_type=authorization_code&scope=openid,lr_partner_apis,lr_partner_rendition_apis",
    'headers' => array(
        'Content-Type' => 'application/x-www-form-urlencoded',
    ),
  ) );

  if ( ! is_wp_error( $response ) ) {
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( isset( $body['error'] ) ) {
      $err = $body['error'];
      if ( isset($body['error_description']) ) {
        $err .= ': ' . $body['error_description'];
      }
      throw new Exception( $err );
    }
    return array(
      'token' => $body['access_token'],
      'expires_in' => $body['expires_in']
    );
  } else {
    throw new Exception( $response->get_error_message() );
  }
}

class JKPGAdobeLRClient {
  private $clientid;
  private $token;

  function __construct($clientid, $token) {
    $this->clientid = $clientid;
    $this->token = $token;
  }

  function parse_response($body) {
    $body = trim(preg_replace('/^ *while *\(1\) *\{ *\} */', '', $body));
    return json_decode($body);
  }

  function get_req_raw($url) {
    $response = wp_remote_get( $url, array(
      'headers' => array(
          'X-API-Key' => $this->clientid,
          'Authorization' => "Bearer " . $this->token,
      ),
    ) );
  
    if ( is_wp_error( $response ) ) {
      throw new Exception( $response->get_error_message() );
    } else if ( wp_remote_retrieve_response_code( $response ) != 200) {
      $code = wp_remote_retrieve_response_code( $response );
      $body = wp_remote_retrieve_body( $response );
      throw new Exception( "$code: $body" );
    }

    return wp_remote_retrieve_body( $response );
  }

  function get_req($url) {
    return $this->parse_response( $this->get_req_raw( $url ) );
  }

  function post_req($url, $extra_hdrs=[]) {
    $hdrs = array(
      'X-API-Key' => $this->clientid,
      'Authorization' => "Bearer " . $this->token,
    );
    $response = wp_remote_post( $url, array(
      'headers' => array_merge($hdrs, $extra_hdrs),
    ) );
  
    if ( is_wp_error( $response ) ) {
      throw new Exception( $response->get_error_message() );
    } else{
      $code = wp_remote_retrieve_response_code( $response );
      if ( $code < 200 || $code > 300) {
        $body = wp_remote_retrieve_body( $response );
        throw new Exception( "$code: $body" );
      }
    }

    return $this->parse_response( wp_remote_retrieve_body( $response ) );
  }

  function get_catalog() {
    return $this->get_req('https://lr.adobe.io/v2/catalog');
  }

  function get_albums($cat, $name_after=NULL) {
    $url = "https://lr.adobe.io/v2/catalogs/$cat/albums";
    if (!is_null($name_after)) {
      $url .= '?name_after=' . $name_after;
    }
    return $this->get_req($url);
  }

  function get_all_albums($cat) {
    $albums = [];
    $n_after = '';
    do {
      $albums_req = $this->get_albums($cat, $n_after);
      $new_albums = $albums_req->resources;
      $albums = array_merge($albums, $new_albums);
      if (count($new_albums) > 0)
        $n_after = end($new_albums)->payload->name;
    } while (count($new_albums) > 0);

    return $albums;
  }

  function get_all_assets($cat, $album) {
    $ids = [];
    $url = "https://lr.adobe.io/v2/catalogs/$cat/albums/$album/assets";
    do {
      $assets_req = $this->get_req($url);

      foreach ($assets_req->resources as $res)
        $ids[] = $res->asset->id;

      if (isset($assets_req->links) && isset($assets_req->links->next))
        $url = $assets_req->links->next->href;
      else
        $url = '';
    } while ($url != '');

    
    $assets = [];
    $id_chunks = array_chunk($ids, 100);
    foreach ($id_chunks as $chunk) {
      $id_s = implode(",", $chunk);
      $url = "https://lr.adobe.io/v2/catalogs/$cat/assets?asset_ids=$id_s";

      do {
        $assets_req = $this->get_req($url);
        $assets += $assets_req->resources;

        if (isset($assets_req->links) && isset($assets_req->links->next))
          $url = $assets_req->links->next->href;
        else
          $url = '';
      } while ($url != '');
    }

    return $assets;
  }

  function request_rendition($cat, $asset) {
    $url = "https://lr.adobe.io/v2/catalogs/$cat/assets/$asset/renditions";
    $hdrs = array('X-Generate-Renditions' => '2560');
    return $this->post_req($url, extra_hdrs: $hdrs);
  }

  function get_rendition($cat, $asset) {
    $url = "https://lr.adobe.io/v2/catalogs/$cat/assets/$asset/renditions/2560";
    return $this->get_req_raw($url);
  }
}
