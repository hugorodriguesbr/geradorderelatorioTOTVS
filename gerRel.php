<?php

class Application_Models_Certificados
{    
    protected $_conRMTOTVS;
    protected $clientSoap;
    protected $optionSoap;

    const baseRM = "CorporeRM"; //Nome da base de dados;

    /**
     * Construtor
     * Cria a conexão com banco de dados
     * 
     */
    public function __construct()
    {
        ini_set('mssql.charset', 'UTF-8');
        $serverT = 'http://seudominio.producao.com.br';

                                        //(nomeServ , user, pass)
        $this->_conRMTOTVS = mssql_connect('serverA', 'user', 'pass'); 

                            //Autenticação válida (usuario e senha utilizado para login no totvs com permissões) 
        $basicParams = array('login' => '','password' => '');
        $this->clientSoap = new SoapClient($serverT.':8051/wsReport/MEX?wsdl',$basicParams);
        $this->optionSoap = array('location' => $serverT.':8051/wsReport/IwsReport'); 
    }

    /**
    * Obtem todos os certificados da dabela SCERTIFICADO TOTVS
    * por CPF
    */
    public function getCertificadosGeral($cpf){
        mssql_select_db(self::baseRM);

        $CertificadosQuery = 
        "SELECT 
            SMODALIDADE.DESCRICAO MODALIDADE,
            SATIVIDADE.DESCRICAO ATIVIDADE,
            SCERTIFICADO.IDOFERTA,
            SCERTIFICADO.IDCERTIFICADO,
            DADOS.NOME PESSOA,
            DADOS.CODPROF,
            DADOS.CODPESSOA,
            DADOS.RA,
            CASE WHEN SCERTIFICADO.CODPESSOA    IS NOT NULL THEN 'Participação como Candidato' 
                 WHEN SCERTIFICADO.CODPROFESSOR IS NOT NULL THEN 'Participação como Professor' 
                 WHEN SCERTIFICADO.RA           IS NOT NULL THEN 'Participação como Aluno' 
            END TIPOINSC
         FROM SCERTIFICADO
         INNER JOIN SATIVIDADE
                 ON SATIVIDADE.CODCOLIGADA = SCERTIFICADO.CODCOLIGADA
                AND SATIVIDADE.IDOFERTA    = SCERTIFICADO.IDOFERTA
         INNER JOIN SMODALIDADE
                 ON SMODALIDADE.CODCOLIGADA = SATIVIDADE.CODCOLIGADA
                AND SMODALIDADE.CODMODALIDADE = SATIVIDADE.CODMODALIDADE
         INNER JOIN(
            SELECT
                PPESSOA.CODIGO CODPESSOA,
                SPROFESSOR.CODPROF,
                SALUNO.RA,
                PPESSOA.NOME,
                SATIVIDADEALUNO.IDOFERTA IDOFERTAALUNO,
                SATIVIDADEPROFESSOR.IDOFERTA IDOFERTAPROF,
                SATIVIDADEPESSOA.IDOFERTA IDOFERTAPESSOA
            FROM PPESSOA (NOLOCK)
            LEFT JOIN SPROFESSOR (NOLOCK)
                   ON SPROFESSOR.CODPESSOA = PPESSOA.CODIGO
            LEFT JOIN SALUNO (NOLOCK)
                   ON SALUNO.CODPESSOA = PPESSOA.CODIGO
            LEFT JOIN SATIVIDADEALUNO
                   ON SATIVIDADEALUNO.RA = SALUNO.RA
            LEFT JOIN SATIVIDADEPROFESSOR
                   ON SATIVIDADEPROFESSOR.CODPROF = SPROFESSOR.CODPROF
            LEFT JOIN SATIVIDADEPESSOA
                   ON SATIVIDADEPESSOA.CODPESSOA = PPESSOA.CODIGO
            WHERE 1=1
               AND PPESSOA.CPF = '$cpf'
               AND (
                    SATIVIDADEALUNO.CUMPRIUATIVIDADE = 'S'
                 OR SATIVIDADEPROFESSOR.CUMPRIUATIVIDADE = 'S'
                 OR SATIVIDADEPESSOA.CUMPRIUATIVIDADE = 'S'
                  )
                
          ) DADOS ON 
                  ( 
                     DADOS.IDOFERTAALUNO =  SCERTIFICADO.IDOFERTA
                  OR DADOS.IDOFERTAPESSOA = SCERTIFICADO.IDOFERTA
                  OR DADOS.IDOFERTAPROF = SCERTIFICADO.IDOFERTA
                  )
                  AND
                  (
                     DADOS.CODPESSOA = SCERTIFICADO.CODPESSOA
                  OR DADOS.CODPROF   = SCERTIFICADO.CODPROFESSOR
                  OR DADOS.RA        = SCERTIFICADO.RA
                  )
        WHERE 1=1 
        AND SCERTIFICADO.IDOFERTA IS NOT NULL";

        $dados = array();
        $result = mssql_query($CertificadosQuery, $this->_conRMTOTVS);
        while ($dado = mssql_fetch_object($result)){
            array_push($dados,array('MODALIDADE'=> $dado->MODALIDADE,
                                   'ATIVIDADE'=> $dado->ATIVIDADE,
                                   'IDOFERTA' => $dado->IDOFERTA,
                                   'PESSOA'=>$dado->PESSOA,
                                   'CODPROF'=> $dado->CODPROF,
                                   'CODPESSOA'=> $dado->CODPESSOA,
                                   'RA'=> $dado->RA,
                                   'TIPOINSC'=> $dado->TIPOINSC,
                                   'IDCERTIFICADO' => $dado->IDCERTIFICADO,
                                   ));
        }
        return json_encode($dados);
    }

  
    /**
      * Consulta SOAP totvs e retorna O RELATÓRIO
      * já inserindo o parametro para gerar o relatório
      */
    public function GetReport($idRelatorio, $idcertificado){
        
        $function = 'GetReportInfo';
        $arguments= array('GetReportInfo' => array(
                          'codColigada'=> 1,
                          'idReport'=> $idRelatorio
                          ));
        $result = (array)$this->clientSoap->__soapCall($function, $arguments, $this->optionSoap);
        $result = (array)$result['GetReportInfoResult'];
        $filter = $result['string'][0];
        $param = $result['string'][1];

        $filter = str_replace('<Value></Value>', "<Value>(SCERTIFICADO.IDCERTIFICADO = $idcertificado)</Value>", $filter);
        $str_filtro = "<FiltersByTable><RptFilterByTablePar><Filter>SCERTIFICADO.IDCERTIFICADO = $idcertificado</Filter><TableName>SCERTIFICADO</TableName></RptFilterByTablePar></FiltersByTable>";
        $filter = str_replace('<FiltersByTable />', $str_filtro, $filter);
        return $this->GenerateReport($idRelatorio, $idcertificado, $filter, $param, $idRelatorio.".pdf");
    }


    /**
      * Gera o relatório no TOTVS
      *
      */
    protected function GenerateReport($idRelatorio, $idcertificado, $filtro, $param, $filename){
        
        $function = 'GenerateReport';
        $arguments= array('GenerateReport' => array(
                'codColigada'=> 1,
                'id'=> $idRelatorio,
                'filters' => $filtro,
                'parameters' => $param,
                'fileName' => $filename
                ));
        $result = (array)$this->clientSoap->__soapCall($function, $arguments, $this->optionSoap);
        $guid = $result['GenerateReportResult'];
            
        return $this->GetGeneratedReportSize($guid, $idcertificado);
    }

    /**
      * Obtem o tamanho do arquivo gerado
      *
      */
    public function GetGeneratedReportSize($guid, $idcertificado){
        $function = 'GetGeneratedReportSize';
        $arguments= array('GetGeneratedReportSize' => array(
                'guid'=>  $guid,
                ));
        $result = (array)$this->clientSoap->__soapCall($function, $arguments, $this->optionSoap);
        return $this->GetFileChunk($guid, $result['GetGeneratedReportSizeResult']);
    }

    /**
      * Obtem o arquivo PDF para ser convertido
      *
      */
    protected function GetFileChunk($guid, $filesize){
        $function = 'GetFileChunk';
        $arguments= array('GetFileChunk' => array(
                    'guid'=> $guid,
                    'offset'=> 0,
                    'length' => $filesize,
                    ));
        $result = (array)$this->clientSoap->__soapCall($function, $arguments, $this->optionSoap);
        return $result['GetFileChunkResult'];
    }
}