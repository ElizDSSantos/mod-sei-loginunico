<?
/**
 * Basis Tecnologia da Informaחדo S/A
 *
 * 12/03/2020 - Behatris Fiorentini e Diego Tesch Gramelich
 *
 */

class UsuarioExternoAPI {
  private $senha;
  private $usuario;
  
  /**
   * @return mixed
   */
  public function getSenha()
  {
    return $this->senha;
  }

  /**
   * @param mixed $senha
   */
  public function setSenha($senha)
  {
    $this->senha = $senha;
  }

  /**
   * @return mixed
   */
  public function getUsuario()
  {
    return $this->usuario;
  }

  /**
   * @param mixed $usuario
   */
  public function setUsuario($usuario)
  {
    $this->usuario = $usuario;
  }

}
?>