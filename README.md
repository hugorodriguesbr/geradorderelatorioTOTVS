# Gerardor de relatório TOTVS

Gerar relatório no RM TOTVS através do WEBSERVICES.
Como exemplo um gerador de certificados.

## Primeiros passos

Você precisa ter um relatório pronto no TOTVS sem filtro.
Utilizar ZEND Framework e incluir este modelo.

### Pré-requisito

PHP 5 or >

### Instalando

INSTALAR ZEND
INCLUIR este código como novo modelo.

Caso não utilize ZEND, verifique o formato de conexão SOAP.


### Utilizando

1) Alterar as variaveis do construtor, corresponsdente ao seu ambiente.
2) Crie um objeto da classe Application_models_certificados
3) chamada no método GetReport passando os parâmetros (idRelatorio: identificador do relatório na tabela GRELBATCH) e (idcertificado: identidicador do certificado a ser gerado)