<?php

/**
 * Exception thrown to simulate wp_safe_redirect + exit during tests.
 */
class WPWC_RedirectException extends Exception
{
  public string $url;

  public function __construct(string $url)
  {
    $this->url = $url;
    parent::__construct("Redirect to: $url");
  }
}
