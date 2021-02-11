# Generated by Selenium IDE
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.common.desired_capabilities import DesiredCapabilities

class Test00LoginExternoVerificar():
  def setup_method(self, method):
    self.driver = webdriver.Remote(command_executor='http://seleniumhub:4444/wd/hub', desired_capabilities=DesiredCapabilities.CHROME)
    self.vars = {}
  
  def teardown_method(self, method):
    self.driver.quit()
  
  def test_00AbrirTelaExternaeLogar(self):
    self.driver.get("http://sei.loginunico.nuvem.gov.br/sei/controlador_externo.php?acao=usuario_externo_logar&id_orgao_acesso_externo=0")
    WebDriverWait(self.driver, 30000).until(expected_conditions.visibility_of_element_located((By.XPATH, "//strong[contains(.,\'ou\')]")))
    assert self.driver.find_element(By.XPATH, "//strong[contains(.,\'ou\')]").text == "ou"
    assert self.driver.find_element(By.LINK_TEXT, "Acessar com").text == "Acessar com"
    elements = self.driver.find_elements(By.XPATH, "//span[@id=\'txtComplementarBtGov\']")
    assert len(elements) > 0
  
  def test_01VerificarLogs(self):
    self.driver.get("http://sei.loginunico.nuvem.gov.br/sip/login.php?sigla_orgao_sistema=ME&sigla_sistema=SEI")
    WebDriverWait(self.driver, 30000).until(expected_conditions.visibility_of_element_located((By.ID, "sbmLogin")))
    self.driver.find_element(By.ID, "txtUsuario").send_keys("teste")
    self.driver.find_element(By.ID, "pwdSenha").send_keys("teste")
    self.driver.find_element(By.ID, "sbmLogin").click()
    self.driver.find_element(By.XPATH, "//a[contains(.,\'Infra\')]").click()
    WebDriverWait(self.driver, 30000).until(expected_conditions.visibility_of_element_located((By.LINK_TEXT, "Log")))
    self.driver.find_element(By.LINK_TEXT, "Log").click()
    WebDriverWait(self.driver, 30000).until(expected_conditions.presence_of_element_located((By.XPATH, "//td[contains(.,\'FIM\')]")))
    elements = self.driver.find_elements(By.XPATH, "//td[contains(.,\'FIM\')]")
    assert len(elements) > 0
  