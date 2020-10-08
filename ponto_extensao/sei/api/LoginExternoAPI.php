<?

class LoginExternoAPI {
  private $sigla;
  private $senha;
  private $token;

  /**
   * @return string
   */
  public function getSigla()
  {
    return $this->sigla;
  }

  /**
   * @param string $sigla
   */
  public function setSigla($sigla)
  {
    $this->sigla = $sigla;
  }

  /**
   * @return string
   */
  public function getSenha()
  {
    return $this->senha;
  }

  /**
   * @param string $senha
   */
  public function setSenha($senha)
  {
    $this->senha = $senha;
  }

  /**
   * @return string
   */
  public function getToken()
  {
    return $this->token;
  }

  /**
   * @param string $token
   */
  public function setToken($token)
  {
    $this->token = $token;
  }
}
?>