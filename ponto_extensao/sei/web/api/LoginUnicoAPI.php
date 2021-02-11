<?
/**
 * Basis Tecnologia da Informaחדo S/A
 *
 * 12/03/2020 - Behatris Fiorentini e Diego Tesch Gramelich
 *
 */

class LoginUnicoAPI {
  private $password;
  private $token;
  
  /**
   * @return mixed
   */
  public function getPassword()
  {
    return $this->password;
  }

  /**
   * @param mixed $password
   */
  public function setPassword($password)
  {
    $this->password = $password;
  }

  /**
   * @return mixed
   */
  public function getToken()
  {
    return $this->token;
  }

  /**
   * @param mixed $token
   */
  public function setToken($token)
  {
    $this->token = $token;
  }

}
?>