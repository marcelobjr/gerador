<?php

require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Response;

$dotenv = new Dotenv\Dotenv(__DIR__.'/..');
$dotenv->load();

$app = new Silex\Application();

$app['config'] = [
    'framework' => $_ENV['PROJECT_FRAMEWORK'],
    'db_connection' => $_ENV['DB_CONNECTION'],
    'db_host' => $_ENV['DB_HOST'],
    'db_name' => $_ENV['DB_NAME'],
    'db_username' => $_ENV['DB_USERNAME'],
    'db_password' => $_ENV['DB_PASSWORD'],
    'table_prefix' => $_ENV['DB_TABLE_PREFIX'],
    'project_name' => $_ENV['PROJECT_NAME'],
    'stub_path' =>  '../src/Stubs/',
    'destination_path' =>  __DIR__.'/arquivos/' //D:\web\www\gerador\public\arquivos

];

$app['debug'] = true;

$app['db'] = function() use ($app) {
    return new \PDO('mysql:host='.$app['config']['db_host'].';dbname='.$app['config']['db_name'].'',''.$app['config']['db_username'].'',''.$app['config']['db_password'].'');
};

$app->get('/', function() use ($app) {
    return new Response(file_get_contents('../resources/views/index.html'), 200);
});

$app->get('/tabelas', function() use ($app) {

    print_r(listarObjTabelas($app));
    exit;

});

$app->get('/limparArquivos', function() use ($app) {

    return new Response(limparDiretorios( $app['config']['destination_path'] ), 200);
});


$app->get('/entityEloquent', function() use ($app) {

    $stub_path = $app['config']['stub_path'].'Entities/Eloquent/';
    $destination_path = $app['config']['destination_path'].'Entities/Eloquent/';
    $arquivosCriados = '';

    $tabelas = listarObjTabelas($app);

    foreach ($tabelas as $tabela) {

        $stubFuncoesBelongsTo = '';
        $flgTimeStamps = false;
        foreach ($tabela->getColunas() as $coluna) {

            if($coluna->getCampoMinusculo() == 'created_at'){
                $flgTimeStamps = true;
            }

            if($coluna->getChave() == "MUL"){

                $tabelasEstrangeiras = $tabela->getTabelasEstrangeiras();
                foreach ($tabelasEstrangeiras as $tabelaEstrangeira) {
                    if (($tabelaEstrangeira->getNome() == $coluna->getCampoTabelaEstrangeira())) {
                        $nmeTabelaEstrangeira = $tabelaEstrangeira->getNomeCamelCaseSingular();
                        break;
                    }
                }

                $replaces = [
                    'NOME_CLASSE_ESTRANGEIRA_CAMEL_CASE_LC_FIRST' => $coluna->getNomeClasseCamelCaseLcFirst($coluna->getCampo()),
                    'NOME_TABELA_FK_CAMEL_CASE' => $nmeTabelaEstrangeira,
                    'NOME_COLUNA_FK'            => $coluna->getCampoChaveEstrangeiraMinusculo(),
                    'NOME_COLUNA'               => $coluna->getCampoMinusculo(),
                ];

                $stubFuncoesBelongsTo .= preencherStub($stub_path, '_FUNCOES_BELONGS_TO', $replaces);
            }
        }

        $stub = file_get_contents($app['config']['stub_path'].'Entities/Eloquent/entity.stub');
        $replaces = [
            'NAMESPACE'            => 'namespace '.$app['config']['project_name'].'\Entities\Eloquent;',
            'CLASS'                => $tabela->getNomeCamelCaseSingular(),
            'PUBLIC_CONNECTION'    => ($app['config']['db_connection'] == 'oracle') ? 'protected $connection = \''.$app['config']['db_connection'].'\';' : '',
            'PUBLIC_SEQUENCE'      => ($app['config']['db_connection'] == 'oracle') ? 'public $sequence = \''.$tabela->getNomeCompletoMinusculo().'_seq\';' : '',
            'PUBLIC_TIMESTAMPS'    => (!$flgTimeStamps) ? 'public $timestamps = false;' : '',
            'NOME_COMPLETO_TABELA' => $tabela->getNomeCompletoMinusculo(),
            'NOME_COLUNA_PK'       => $tabela->getChavePrimariaMinusculo(),
            'COLUNAS_SEM_PK'       => $tabela->getColunasCamposSemPkPorVirgulaMinusculo(),
            'FUNCOES_BELONGS_TO'   => $stubFuncoesBelongsTo
        ];

        foreach ($replaces as $search => $replace) {
            $stub = str_replace('$' . strtoupper($search) . '$', $replace, $stub);
        }

        $arquivo = $destination_path.$tabela->getNomeCamelCaseSingular().'.php';
        criarArquivo($stub, $arquivo);
        $arquivosCriados .= $arquivo.'<br>';

    }

    return new Response($arquivosCriados, 200);

});

$app->get('/entityOrmSefaz', function() use ($app) {

    $stub_path = $app['config']['stub_path'].'Entities/OrmSefaz/';
    $destination_path = $app['config']['destination_path'].'Entities/OrmSefaz/';
    $tabelas = listarObjTabelas($app);

    $arquivosCriados = '';
    foreach ($tabelas as $tabela) {
        $stubPrivateNomeColuna = '';
        $stubConstrutorNomeColuna = '';
        $stubGetSet = '';
        $stubConstrutorColunaEstrangeiraDetalhada = '';
        $stubConstrutorColunaEstrangeira = '';
        foreach ($tabela->getColunas() as $coluna) {

          $replaces = [
            'NOME_COLUNA_CAMEL_CASE_LC_FIRST' => $coluna->getCampoCamelCaseLcFirst()
          ];

          $stubPrivateNomeColuna .= preencherStub($stub_path, 'PRIVATE_NOME_COLUNAS', $replaces);

          $replaces = [
            'NOME_COLUNA_CAMEL_CASE_LC_FIRST' => $coluna->getCampoCamelCaseLcFirst(),
            'NOME_COLUNA_MAIUSCULO' => $coluna->getCampoMaiusculo()
          ];

          $stubConstrutorNomeColuna .= preencherStub($stub_path, 'CONSTRUTOR_NOME_COLUNA', $replaces);

          $replaces = [
            'NOME_COLUNA_CAMEL_CASE_LC_FIRST' => $coluna->getCampoCamelCaseLcFirst(),
            'NOME_COLUNA_CAMEL_CASE' => $coluna->getCampoCamelCase()
          ];

          $stubGetSet .= preencherStub($stub_path, 'GET_SET', $replaces);

          if($coluna->getChave() == "MUL"){
            $tabelasEstrangeiras = $tabela->getTabelasEstrangeiras();
            foreach ($tabelasEstrangeiras as $tabelaEstrangeira) {
                $tabEstColunas = $tabelaEstrangeira->getColunas();
                foreach ($tabEstColunas as $tabEstColuna) {
                    if($tabEstColuna->getChave() == "PRI" && $tabEstColuna->getCampo() == $coluna->getCampoChaveEstrangeira()){
                        $nmeTabelaEstrangeira = $tabelaEstrangeira->getNomeCamelCaseSingular();
                    }
                }
            }

            $replaces = [
              'NOME_TABELA_ESTRANGEIRA' => $nmeTabelaEstrangeira,
              'COLUNA_TABELA_ESTRANGEIRA_CAMEL_CASE' => $coluna->getCampoChaveEstrangeiraCamelCase(),
              'NOME_COLUNA_MAIUSCULO' => $coluna->getCampoMaiusculo(),
              'NOME_CLASSE_ESTRANGEIRA' => lcfirst(substr($coluna->getCampoCamelCaseLcFirst(),2))
            ];

            $stubConstrutorColunaEstrangeiraDetalhada .= preencherStub($stub_path, 'CONSTRUTOR_COLUNA_ESTRANGEIRA_DETALHADA', $replaces);

            $replaces = [
              'NOME_TABELA_ESTRANGEIRA' => $nmeTabelaEstrangeira,
              'COLUNA_TABELA_ESTRANGEIRA_CAMEL_CASE' => $coluna->getCampoChaveEstrangeiraCamelCase(),
              'NOME_COLUNA_MAIUSCULO' => $coluna->getCampoMaiusculo(),
              'NOME_CLASSE_ESTRANGEIRA' => lcfirst(substr($coluna->getCampoCamelCaseLcFirst(),2))
            ];

            $stubConstrutorColunaEstrangeira .= preencherStub($stub_path, 'CONSTRUTOR_COLUNA_ESTRANGEIRA', $replaces);

          }

        }

        $replaces = [
            'NAMESPACE'            => 'namespace '.$app['config']['project_name'].'\Entities\OrmSefaz;',
            'CLASS'                => $tabela->getNomeCamelCaseSingular(),
            'PRIVATE_NOME_COLUNAS' => $stubPrivateNomeColuna,
            'CONSTRUTOR_NOME_COLUNA' => $stubConstrutorNomeColuna,
            'GET_SET'              => $stubGetSet,
            'CONSTRUTOR_COLUNA_ESTRANGEIRA_DETALHADA' => $stubConstrutorColunaEstrangeiraDetalhada,
            'CONSTRUTOR_COLUNA_ESTRANGEIRA' => $stubConstrutorColunaEstrangeira
        ];

        $stub = preencherStub($stub_path, 'entity', $replaces);

        $arquivo = $destination_path.$tabela->getNomeCamelCaseSingular().'.php';
        criarArquivo($stub, $arquivo);
        $arquivosCriados .= $arquivo.'<br>';

    }

    return new Response($arquivosCriados, 200);

});

$app->get('/presenter', function() use ($app) {

    $stub_path = $app['config']['stub_path'].'Presenters/';
    $destination_path = $app['config']['destination_path'].'Presenters/';
    $arquivosCriados = '';

    $tabelas = listarObjTabelas($app);

    foreach ($tabelas as $tabela) {

        $replaces = [
            'NAMESPACE'            => 'namespace '.$app['config']['project_name'].'\Presenters;',
            'CLASS'                => $tabela->getNomeCamelCaseSingular(),
            'PROJETO'              => $app['config']['project_name'],
        ];

        $stub = preencherStub($stub_path, 'presenter', $replaces);

        $arquivo = $destination_path.$tabela->getNomeCamelCaseSingular().'Presenter.php';
        criarArquivo($stub, $arquivo);
        $arquivosCriados .= $arquivo.'<br>';

    }

    return new Response($arquivosCriados, 200);

});

$app->get('/repositoryInterface', function() use ($app) {

    $stub_path = $app['config']['stub_path'].'Repositories/Interfaces/';
    $destination_path = $app['config']['destination_path'].'Repositories/Interfaces/';
    $arquivosCriados = '';

    $tabelas = listarObjTabelas($app);

    foreach ($tabelas as $tabela) {

        $replaces = [
            'NAMESPACE' => 'namespace '.$app['config']['project_name'].'\Repositories\Interfaces;',
            'CLASS'     => $tabela->getNomeCamelCaseSingular(),
        ];

        $stub = preencherStub($stub_path, 'interface', $replaces);

        $arquivo = $destination_path.$tabela->getNomeCamelCaseSingular().'Interface.php';
        criarArquivo($stub, $arquivo);
        $arquivosCriados .= $arquivo.'<br>';

    }

    //Cria o BaseInterface.php
    $replaces = [
        'NAMESPACE' => 'namespace '.$app['config']['project_name'].'\Repositories\Interfaces;',
    ];

    $stub = preencherStub($stub_path, 'baseInterfaceV1', $replaces);

    $arquivo = $destination_path.'BaseInterface.php';
    criarArquivo($stub, $arquivo);
    $arquivosCriados .= $arquivo.'<br>';

    return new Response($arquivosCriados, 200);

});


$app->get('/provider', function() use ($app) {

    $stub_path = $app['config']['stub_path'].'Providers/';
    $destination_path = $app['config']['destination_path'].'Providers/';
    $arquivosCriados = '';

    $tabelas = listarObjTabelas($app);

    $stubBinds = '';
    foreach ($tabelas as $tabela) {

        $replaces = [
            'PATH_INTERFACE_REPOSITORY_CLASS' => '\\'.$app['config']['project_name'].'\Repositories\\Interfaces\\'.$tabela->getNomeCamelCaseSingular().'Interface::class',
            'PATH_REPOSITORY_CLASS'           => '\\'.$app['config']['project_name'].'\Repositories\\Eloquent\\'.$tabela->getNomeCamelCaseSingular().'Repository::class',
        ];

        $stubBinds .= preencherStub($stub_path, '_BINDS', $replaces);
    }

    $replaces = [
        'NAMESPACE' => 'namespace '.$app['config']['project_name'].'\Providers;',
        'CLASS'     => $app['config']['project_name'],
        'BINDS'     => $stubBinds
    ];

    $stub = preencherStub($stub_path, 'provider', $replaces);

    $arquivo = $destination_path.$app['config']['project_name'].'RepositoryProvider.php';
    criarArquivo($stub, $arquivo);
    $arquivosCriados .= $arquivo.'<br>';

    return new Response($arquivosCriados, 200);

});

$app->get('/repositoryEloquent', function() use ($app) {

    $stub_path = $app['config']['stub_path'].'Repositories/Eloquent/';
    $destination_path = $app['config']['destination_path'].'Repositories/Eloquent/';
    $arquivosCriados = '';

    $tabelas = listarObjTabelas($app);

    foreach ($tabelas as $tabela) {

        $replaces = [
            'NAMESPACE' => 'namespace ' . $app['config']['project_name'] . '\Repositories\Eloquent;',
            'CLASS' => $tabela->getNomeCamelCaseSingular(),
            'PROJETO' => $app['config']['project_name'],
            'COLUNAS' => '\''.$tabela->getChavePrimariaMinusculo().'\', '.$tabela->getColunasCamposSemPkPorVirgulaMinusculo()
        ];

        $stub = preencherStub($stub_path, 'repositoryV1', $replaces);

        $arquivo = $destination_path . $tabela->getNomeCamelCaseSingular().'Repository.php';
        criarArquivo($stub, $arquivo);
        $arquivosCriados .= $arquivo.'<br>';

    }

    $replaces = [
        'NAMESPACE' => 'namespace '.$app['config']['project_name'].'\Repositories\Eloquent;',
    ];

    $stub = preencherStub($stub_path, 'baseRepositoryV1', $replaces);

    $arquivo = $destination_path.'BaseRepository.php';
    criarArquivo($stub, $arquivo);
    $arquivosCriados .= $arquivo.'<br>';

    return new Response($arquivosCriados, 200);

});

$app->get('/transformer', function() use ($app) {

    $stub_path = $app['config']['stub_path'].'Transformers/';
    $destination_path = $app['config']['destination_path'].'Transformers/';
    $arquivosCriados = '';

    $tabelas = listarObjTabelas($app);

    foreach ($tabelas as $tabela) {

        $stubDefaultIncludes = '';
        $stubReturnTransformer = '';
        $stubFunctionIncludes = '';

        $replaces = [
            'TABELA_ESTRANGEIRA_SINGULAR_CAMEL_CASE' => $app['config']['project_name'].'\Entities\Eloquent\\'.$tabela->getNomeCamelCaseSingular(),
        ];
        $stubUseEntities = preencherStub($stub_path, '_USE_ENTITIES', $replaces);

        foreach ($tabela->getColunas() as $coluna) {

            if ($coluna->getChave() == "MUL") {

                $tabelasEstrangeiras = $tabela->getTabelasEstrangeiras();
                foreach ($tabelasEstrangeiras as $tabelaEstrangeira) {
                    if (($tabelaEstrangeira->getNome() == $coluna->getCampoTabelaEstrangeira())) {

                        $replaces = [
                            'TABELA_ESTRANGEIRA_SINGULAR_CAMEL_CASE' => $app['config']['project_name'].'\Entities\Eloquent\\'.$tabelaEstrangeira->getNomeCamelCaseSingular(),
                        ];
                        $stubUseEntities .= preencherStub($stub_path, '_USE_ENTITIES', $replaces);


                        $replaces = [
                            'TABELA_ESTRANGEIRA_SINGULAR_CAMEL_CASE_LC_FIRST' => $tabelaEstrangeira->getNomeCamelCaseLcFirstSingular(),
                        ];
                        $stubDefaultIncludes .= preencherStub($stub_path, '_DEFAULT_INCLUDES', $replaces);

                        $replaces = [
                            'CLASS' => $tabela->getNomeCamelCaseSingular(),
                            'TABELA_ESTRANGEIRA_SINGULAR_CAMEL_CASE_LC_FIRST' => $tabelaEstrangeira->getNomeCamelCaseLcFirstSingular(),
                            'TABELA_ESTRANGEIRA_SINGULAR_CAMEL_CASE' => $tabelaEstrangeira->getNomeCamelCaseSingular(),
                        ];
                        $stubFunctionIncludes .= preencherStub($stub_path, '_FUNCTION_INCLUDES', $replaces);

                        break;
                    }
                }
            }

            $replaces = [
                'NOME_COLUNA_CAMEL_CASE_LC_FIRST' => $coluna->getCampoCamelCaseLcFirst(),
                'NOME_COLUNA_MINUSCULO' => $coluna->getCampoMinusculo(),
            ];
            $stubReturnTransformer .= preencherStub($stub_path, '_RETURN_TRANSFORM', $replaces);

        }


        $replaces = [
            'NAMESPACE'         => 'namespace '.$app['config']['project_name'].'\Transformers;',
            'CLASS'             => $tabela->getNomeCamelCaseSingular(),
            '_USE_ENTITIES'     => $stubUseEntities,
            '_DEFAULT_INCLUDES' => $stubDefaultIncludes,
            '_RETURN_TRANSFORM' => $stubReturnTransformer,
            '_FUNCTION_INCLUDES' => $stubFunctionIncludes
        ];
        $stub = preencherStub($stub_path, 'transformer', $replaces);

        $arquivo = $destination_path.$tabela->getNomeCamelCaseSingular().'Transformer.php';
        criarArquivo($stub, $arquivo);
        $arquivosCriados .= $arquivo.'<br>';
    }

    return new Response($arquivosCriados, 200);

});

$app->get('/validator', function() use ($app) {

    $stub_path = $app['config']['stub_path'].'Validators/';
    $destination_path = $app['config']['destination_path'].'Validators/';
    $arquivosCriados = '';

    $tabelas = listarObjTabelas($app);

    foreach ($tabelas as $tabela) {

        $stubRules = '';

        foreach ($tabela->getColunas() as $coluna) {

            if (($coluna->getChave() != "PRI") && ($coluna->getCampoMinusculo() != 'created_at') && ($coluna->getCampoMinusculo() != 'updated_at')) {

                $replaces = [
                    'NOME_COLUNA_MINUSCULO' => $coluna->getCampoMinusculo(),
                    'REGRA_VALIDATOR' => $coluna->getRegraValidator(),
                ];

                $stubRules .= preencherStub($stub_path, '_RULES', $replaces);
            }
        }

        $replaces = [
            'NAMESPACE' => 'namespace '.$app['config']['project_name'].'\Validators;',
            'CLASS'     => $tabela->getNomeCamelCaseSingular(),
            '_RULES'    => $stubRules,
        ];
        $stub = preencherStub($stub_path, 'validator', $replaces);

        $arquivo = $destination_path.$tabela->getNomeCamelCaseSingular().'Validator.php';
        criarArquivo($stub, $arquivo);
        $arquivosCriados .= $arquivo.'<br>';
    }

    return new Response($arquivosCriados, 200);

});

$app->get('/service', function() use ($app) {

    $stub_path = $app['config']['stub_path'].'Services/';
    $destination_path = $app['config']['destination_path'].'Services/';
    $arquivosCriados = '';

    $tabelas = listarObjTabelas($app);

    foreach ($tabelas as $tabela) {

        $replaces = [
            'NAMESPACE' => 'namespace '.$app['config']['project_name'].'\Services;',
            'PROJETO' => $app['config']['project_name'],
            'CLASS'     => $tabela->getNomeCamelCaseSingular(),
        ];
        $stub = preencherStub($stub_path, 'service', $replaces);

        $arquivo = $destination_path.$tabela->getNomeCamelCaseSingular().'Service.php';
        criarArquivo($stub, $arquivo);
        $arquivosCriados .= $arquivo.'<br>';
    }

    $replaces = [
        'NAMESPACE' => 'namespace '.$app['config']['project_name'].'\Services;',
    ];
    $stub = preencherStub($stub_path, 'baseService', $replaces);

    $arquivo = $destination_path.'BaseService.php';
    criarArquivo($stub, $arquivo);
    $arquivosCriados .= $arquivo.'<br>';

    return new Response($arquivosCriados, 200);

});

$app->get('/controller', function() use ($app) {

    $stub_path = $app['config']['stub_path'].'Http/Controllers/';
    $destination_path = $app['config']['destination_path'].'Http/Controllers/';
    $arquivosCriados = '';

    $tabelas = listarObjTabelas($app);

    foreach ($tabelas as $tabela) {

        $replaces = [
            'NAMESPACE' => 'namespace '.$app['config']['project_name'].'\Http\Controllers;',
            'PROJETO' => $app['config']['project_name'],
            'CLASS'     => $tabela->getNomeCamelCaseSingular(),
        ];
        $stub = preencherStub($stub_path, 'controller', $replaces);

        $arquivo = $destination_path.$tabela->getNomeCamelCaseSingular().'Controller.php';
        criarArquivo($stub, $arquivo);
        $arquivosCriados .= $arquivo.'<br>';
    }

    $replaces = [
        'PROJETO' => $app['config']['project_name'],
    ];
    $stub = preencherStub($stub_path, 'controllerPrincipal', $replaces);

    $arquivo = $destination_path.'Controller.php';
    criarArquivo($stub, $arquivo);
    $arquivosCriados .= $arquivo.'<br>';

    return new Response($arquivosCriados, 200);

});

$app->get('/route', function() use ($app) {

    $stub_path = $app['config']['stub_path'].'Http/';
    $destination_path = $app['config']['destination_path'].'Http/';
    $arquivosCriados = '';

    $tabelas = listarObjTabelas($app);

    $stubRotas = '';
    foreach ($tabelas as $tabela) {

        $replaces = [
            'NOME_CAMEL_CASE_LC_FIRST' => $tabela->getNomeCamelCaseLcFirstSingular(),
            'NOME_CAMEL_CASE' => $tabela->getNomeCamelCaseSingular(),
        ];
        $stubRotas .= preencherStub($stub_path, ($app['config']['framework']=='laravel')?'_ROTAS_LARAVEL':'_ROTAS_LUMEN', $replaces);
    }

    $replaces = [
        '_ROTAS' => $stubRotas,
    ];
    $stub = preencherStub($stub_path, 'route', $replaces);

    $arquivo = $destination_path.'routes.php';
    criarArquivo($stub, $arquivo);
    $arquivosCriados .= $arquivo.'<br>';

    return new Response($arquivosCriados, 200);

});

$app->get('/langPt-brValidation', function() use ($app) {
    $stub_path = $app['config']['stub_path'].'resources/lang/pt-br/';
    $destination_path = $app['config']['destination_path'].'resources/lang/pt-br/';
    $arquivosCriados = '';
    
    $tabelas = listarObjTabelas($app);
    $arrayNmeColunas = array();
    $stubAttributes = '';
    foreach ($tabelas as $tabela) {

        foreach ($tabela->getColunas() as $coluna) {

            $campoCamelCaseLcFirstSingular = $coluna->getCampoCamelCaseLcFirst();
            if(!in_array($campoCamelCaseLcFirstSingular,$arrayNmeColunas)){
                array_push($arrayNmeColunas, $campoCamelCaseLcFirstSingular);

                $nmeColuna = $coluna->getCampo();
                $nmeColuna = str_replace('_', ' ', $nmeColuna);
                $nmeColuna = ucwords(strtolower($nmeColuna));
                $arrayNmeColuna = explode(" ",$nmeColuna);
                switch ($arrayNmeColuna[0]){
                    case "Vlr":
                        $arrayNmeColuna[0] = "Valor";
                        break;
                    case "Dat":
                        $arrayNmeColuna[0] = "Data de";
                        break;
                    default:
                        array_shift($arrayNmeColuna);
                }
                
                $nmeColuna = implode(" ",$arrayNmeColuna);

                $replaces = [
                    'NOME_COLUNA_CAMEL_CASE_LC_FIRST' => $coluna->getCampoCamelCaseLcFirst(),
                    'NOME_COLUNA_CAMEL_CASE_WITH_SPACES' => $nmeColuna,
                ];

                $stubAttributes .= preencherStub($stub_path, "ATTRIBUTES", $replaces);
            }
        }
    }
    
    $replaces = [
        'ATTRIBUTES' => $stubAttributes,
    ];
    $stub = preencherStub($stub_path, 'validation', $replaces);
    
    $arquivo = $destination_path.'validation.php';
    criarArquivo($stub, $arquivo);
    $arquivosCriados .= $arquivo.'<br>';
    
    return new Response($arquivosCriados, 200);
});

$app->get('/database/migrations', function() use ($app) {

    $stub_path = $app['config']['stub_path'].'database/migrations/';
    $destination_path = $app['config']['destination_path'].'database/migrations/';
    $arquivosCriados = '';

    $tabelas = listarObjTabelas($app);
    $migrationFkStub = '';

    foreach ($tabelas as $tabela) {

        $stubColunas = '';
        $migrationFK = '';
        $flgTimeStamps = false;


        foreach ($tabela->getColunas() as $coluna) {
            
            $migrationColuna = '        $table';

            if(($coluna->getCampoMinusculo() == 'created_at') || ($coluna->getCampoMinusculo() == 'updated_at')) {
                $flgTimeStamps = true;
            } else {


                if($coluna->getChave() == 'PRI') {
                    $migrationColuna .= "->increments('".$coluna->getCampoMinusculo()."')";
                } else {

                    if(substr($coluna->getTipo(),0,3) == 'int') {
                        $migrationColuna .= "->integer('".$coluna->getCampoMinusculo()."')";
                    } elseif (substr($coluna->getTipo(),0,4) == 'text'){
                        $migrationColuna .= "->text('".$coluna->getCampoMinusculo()."')";
                    } else {

                        if(substr($coluna->getTipo(),0,7) == 'varchar'){
                            $tipoMigration = 'string';
                            preg_match('#\((.*?)\)#', $coluna->getTipo(), $match); //Pega o que estiver entre parentesis
                            $tamanho = (isset($match[1])) ?  $match[1] : '';
                        } else {

                            $tipoMigration = explode('(',$coluna->getTipo())[0];
                            preg_match('#\((.*?)\)#', $coluna->getTipo(), $match); //Pega o que estiver entre parentesis
                            $tamanho = (isset($match[1])) ?  $match[1] : '';
                            
                        }

                        if(isset($tamanho) && $tamanho !='') {
                            $migrationColuna .= "->".$tipoMigration."('".$coluna->getCampoMinusculo()."', ".$tamanho.")";
                            $tamanho = '';
                        } else {
                            $migrationColuna .= "->".$tipoMigration."('".$coluna->getCampoMinusculo()."')";
                        }

                    }

                    if((substr($coluna->getTipo(),-8) == 'unsigned') || ($coluna->getChave() == 'MUL')){
                        $migrationColuna .= "->unsigned()";
                    }

                    if($coluna->isNulo() == 1) {
                        $migrationColuna .= "->nullable()";
                    }

                    if($coluna->getChave() == 'MUL') {
                        $migrationFK .= "
            \$table->foreign('".$coluna->getCampoMinusculo()."')->references('".$coluna->getCampoChaveEstrangeiraMinusculo()."')->on('".$coluna->getCampoTabelaEstrangeiraMinusculo()."');";
                        //$migrationColuna .= "\$table->foreign('".$coluna->getCampoMinusculo()."')->references('".$coluna->getCampoChaveEstrangeiraMinusculo()."')->on('".$coluna->getCampoTabelaEstrangeiraMinusculo()."');";
                    }
                }

                
                $migrationColuna .= ";";

                $replaces = [
                    'MIGRATION_COLUNA' => $migrationColuna,
                ];

                $stubColunas .= preencherStub($stub_path, '_COLUNAS', $replaces);

            }

        }

        $replaces = [
            'CLASS'                => $tabela->getNomeCamelCaseSingular(),
            'NOME_COMPLETO_TABELA' => $tabela->getNomeCompletoMinusculo(),
            '_COLUNAS'   => $stubColunas,
            'TIMESTAMPS' => ($flgTimeStamps) ? '$table->timestamps();' : '',
//            'MIGRATION_FK' => ($migrationFK!='') ? 'Schema::table(\''.$tabela->getNomeCompletoMinusculo().'\', function (Blueprint $table) {'.$migrationFK.'});' : '',
        ];
        $stub = preencherStub($stub_path, 'migration', $replaces);

        $arquivo = $destination_path.date('Y_i_d_hms').'_create_'.$tabela->getNomeCompletoMinusculo().'_table.php';
        criarArquivo($stub, $arquivo);
        $arquivosCriados .= $arquivo.'<br>';

        //Criar stub somente com as chaves estrangeiras
        $migrationFkStub .= ($migrationFK!='') ? 'Schema::table(\''.$tabela->getNomeCompletoMinusculo().'\', function (Blueprint $table) {'.$migrationFK.'
        });
        
        ' : '';

    }

    $replaces = [
        'MIGRATION_FK' => $migrationFkStub,
    ];
    $stub = preencherStub($stub_path, 'migration_fk', $replaces);
    $anoMais1 = date("Y") + 1;
    $arquivo = $destination_path.$anoMais1.'_'.date('i_d_hms').'0_create_fk_all_table.php';
    criarArquivo($stub, $arquivo);
    $arquivosCriados .= $arquivo.'<br>';

    return new Response($arquivosCriados, 200);

});

$app->run();
