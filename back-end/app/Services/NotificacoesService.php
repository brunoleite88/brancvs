<?php

namespace App\Services;

use App\Models\Notificacao;
use App\Models\NotificacaoDestinatario;
use App\Models\Usuario;

class NotificacoesService 
{

    public $notificacoes = [];

    public function __construct() {
        $this->notificacoes = [
            "PRAZO_RELATORIO_CORREGEDORIA" => [
                "descricao" => "Alerta de prazo e pendência do Relatório Mensal da Corregedoria",
                "destinatarios" => (fn(&$params) => []),
                "unidade" => (fn(&$params) => null),
                "validacao" => (fn(&$params) => true),
                "dataset" => [["field" => "competencia", "label" => "Competência"], ["field" => "prazo", "label" => "Prazo limite"]],
                "datasource" => (fn(&$params) => ["competencia" => $params["competencia"] ?? "", "prazo" => $params["prazo"] ?? ""]),
                "template" => "Seu Relatório Mensal de Atividades ({{competencia}}) encontra-se pendente de consolidação e assinatura até {{prazo}}."
            ],
            "HOMOLOGACAO_CORREGEDORIA" => [
                "descricao" => "Alerta de relatórios mensais aguardando homologação da Corregedoria",
                "destinatarios" => (fn(&$params) => []),
                "unidade" => (fn(&$params) => null),
                "validacao" => (fn(&$params) => true),
                "dataset" => [["field" => "quantidade", "label" => "Quantidade pendente"]],
                "datasource" => (fn(&$params) => ["quantidade" => $params["quantidade"] ?? 0]),
                "template" => "Existem {{quantidade}} relatório(s) mensal(is) assinado(s) aguardando análise e homologação no Painel da Corregedoria."
            ],
            "PRAZO_ATIVIDADES_PLANOS" => [
                "descricao" => "Alerta de atividades da Agenda de Tarefas e Planos com ações ou prazos pendentes",
                "destinatarios" => (fn(&$params) => []),
                "unidade" => (fn(&$params) => null),
                "validacao" => (fn(&$params) => true),
                "dataset" => [["field" => "resumo", "label" => "Resumo das pendências"]],
                "datasource" => (fn(&$params) => ["resumo" => $params["resumo"] ?? ""]),
                "template" => "Você possui atividades ou validações de planos com prazo em andamento: {{resumo}}."
            ],
            "PRAZO_INICIATIVAS_MARCOS" => [
                "descricao" => "Alerta de marcos estratégicos e iniciativas prioritárias de curto prazo da Unidade",
                "destinatarios" => (fn(&$params) => []),
                "unidade" => (fn(&$params) => null),
                "validacao" => (fn(&$params) => true),
                "dataset" => [["field" => "unidade_sigla", "label" => "Sigla da Unidade"], ["field" => "quantidade", "label" => "Total de iniciativas"]],
                "datasource" => (fn(&$params) => ["unidade_sigla" => $params["unidade_sigla"] ?? "", "quantidade" => $params["quantidade"] ?? 0]),
                "template" => "A unidade {{unidade_sigla}} possui {{quantidade}} iniciativa(s) prioritária(s) de curto prazo em acompanhamento no ciclo atual."
            ],
            "DMD_DISTRIBUICAO" => [
                "descricao" => "Notificação de distribuição do registro de execução",
                "destinatarios" => (fn(&$params) => [$params["atividade"]->usuario]),
                "unidade" => (fn(&$params) => $params["atividade"]->unidade),
                "validacao" => (fn(&$params) => !empty($params["atividade"]->usuario_id)),
                "dataset" => [["field" => "atividade_numero", "label" => "Número do registro"]],
                "datasource" => (fn(&$params) => ["atividade_numero" => $params["atividade"]->numero]),
                "template" => "Um novo registro de execução foi atribuído a você, acesse o Sistema PGE-MA para visualizá-lo! (ID: #{{atividade_numero}})"
            ],
            "DMD_MODIFICACAO" => [
                "descricao" => "Notificação de modificações no registro de execução",
                "destinatarios" => (fn(&$params) => [$params["atividade"]->usuario, $params["atividade"]->atividadente]),
                "unidade" => (fn(&$params) => $params["atividade"]->unidade),
                "validacao" => (fn(&$params) => true),
                "dataset" => [["field" => "atividade_numero", "label" => "Número do registro"], ["field" => "atividade_responsavel", "label" => "Nome do responsável"]],
                "datasource" => (fn(&$params) => ["atividade_numero" => $params["atividade"]->numero, "atividade_responsavel" => $params["atividade"]->usuario->nome]),
                "template" => "O registro de execução #{{atividade_numero}}, atribuído à {{atividade_responsavel}}, foi atualizado, acesse o Sistema PGE-MA para visualizá-lo!"
            ],         
            "DMD_COMENTARIO" => [
                "descricao" => "Notificação de comentário no registro de execução",
                "destinatarios" => (fn(&$params) => [$params["atividade"]->usuario, $params["atividade"]->atividadente]),
                "unidade" => (fn(&$params) => $params["atividade"]->unidade),
                "validacao" => (fn(&$params) => true),
                "dataset" => [["field" => "atividade_numero", "label" => "Número do registro"], ["field" => "atividade_responsavel", "label" => "Nome do responsável"]],
                "datasource" => (fn(&$params) => ["atividade_numero" => $params["atividade"]->numero, "atividade_responsavel" => $params["atividade"]->usuario->nome]),
                "template" => "Foi inserido um comentário no registro de execução #{{atividade_numero}}, atribuído a {{atividade_responsavel}}, acesse o Sistema PGE-MA para visualizá-lo!"
            ],         
            "DMD_CONCLUSAO" => [
                "descricao" => "Notificação de conclusão do registro de execução",
                "destinatarios" => (fn(&$params) => [$params["atividade"]->atividadente]),
                "unidade" => (fn(&$params) => $params["atividade"]->unidade),
                "validacao" => (fn(&$params) => true),
                "dataset" => [["field" => "atividade_numero", "label" => "Número do registro"], ["field" => "atividade_responsavel", "label" => "Nome do responsável"]],
                "datasource" => (fn(&$params) => ["atividade_numero" => $params["atividade"]->numero, "atividade_responsavel" => $params["atividade"]->usuario->nome]),
                "template" => "O registro de execução #{{atividade_numero}}, atribuído à\ao {{atividade_responsavel}}, foi concluído, acesse o Sistema PGE-MA para visualizá-lo!"
            ],         
            "DMD_AVALIACAO" => [
                "descricao" => "Notificação de avaliação do registro de execução",
                "destinatarios" => (fn(&$params) => [$params["atividade"]->usuario]),
                "unidade" => (fn(&$params) => $params["atividade"]->unidade),
                "validacao" => (fn(&$params) => true),
                "dataset" => [["field" => "atividade_numero", "label" => "Número do registro"]],
                "datasource" => (fn(&$params) => ["atividade_numero" => $params["atividade"]->numero]),
                "template" => "Seu registro de execução #{{atividade_numero}} foi avaliado, acesse o Sistema PGE-MA para acompanhá-lo!"
            ],
            "PRG_PART_HABILITACAO" => [
                "descricao" => "Notificação de habilitação do participante",
                "destinatarios" => (fn(&$params) => [$params["programa_participante"]->usuario]),
                "unidade" => (fn(&$params) => $params["programa"]->unidade),
                "validacao" => (fn(&$params) => true),
                "dataset" => [["field" => "programa_nome", "label" => "Nome do Programa"]],
                "datasource" => (fn(&$params) => ["programa_nome" => $params["programa"]->nome]),
                "template" => "Você foi habilitado no programa {{programa_nome}}. Acesse o Sistema PGE-MA para continuar o seu trabalho!"
            ],
            "PENT_ALTERACAO" => [
                "descricao" => "Notificação de alteração no plano de ação setorial",
                "destinatarios" => (fn(&$params) => $params["destinatarios"]),
                "unidade" => (fn(&$params) => $params["plano_entrega"]->unidade),
                "validacao" => (fn(&$params) => true),
                "dataset" => [["field" => "plano_numero", "label" => "Número do plano de ação setorial"],["field" => "servidor_nome", "label" => "Nome do Agente Público"],["field" => "servidor_matricula", "label" => "Matrícula do Agente Público"]],
                "datasource" => (fn(&$params) => ["plano_numero" => $params["plano_entrega"]->numero, "servidor_nome" => $params["servidor"]->nome, "servidor_matricula" => $params["servidor"]->matricula]),
                "template" => "O plano de ação setorial #{{ plano_numero }} foi alterado nesta data pelo agente público {{ servidor_nome }}, matrícula {{ servidor_matricula }}."
            ]
        ];
    }

    public function getOrDefault($key, $array, $default = true) {
        return !array_key_exists($key, $array) ? $default : $array[$key];
    }

    public function send($code, $params, $unidade = null) {
        $notificacaoDef = $this->getOrDefault($code, $this->notificacoes, null);
        if(!empty($notificacaoDef)) {
            $enviados = [
                "petrvs" => [],
                "email" => [],
                "whatsapp" => []
            ];
            $destinatarios = $notificacaoDef["destinatarios"]($params);
            $unidade = $unidade ?? $notificacaoDef["unidade"]($params);
            $entidade = $unidade?->entidade;
            $notificacaoEntidade = $entidade?->notificacoesTemplates->where("codigo", $code)->first() ?? ["enviar" => true, "template" => null];
            $notificacaoUnidade = $unidade?->notificacoesTemplates->where("codigo", $code)->first() ?? ["enviar" => true, "template" => null];
            $template = $notificacaoUnidade["template"] ?? $notificacaoEntidade["template"] ?? $notificacaoDef["template"];
            $message = $this->applyParams($template, $notificacaoDef["datasource"]($params));
            if(!empty($entidade) && $notificacaoEntidade["enviar"] && $notificacaoUnidade["enviar"]) {
                $config = config("notificacoes");
                $notificacao = new Notificacao([
                    "codigo" => $code,
                    "data_registro" => now(),
                    "mensagem" => $message
                ]);
                $notificacao->save();
                foreach($destinatarios as $destinatario) {
                    if (!$destinatario instanceof Usuario) {
                        continue;
                    }
                    if($config["petrvs"]["enviar"] && $this->getOrDefault("enviar_petrvs", $destinatario->notificacoes) && !in_array($destinatario->id, $enviados["petrvs"])) {
                        NotificacaoDestinatario::create([
                            "tipo" => "PETRVS",
                            "notificacao_id" => $notificacao->id,
                            "usuario_id" => $destinatario->id
                        ])->save();
                        $enviados["petrvs"][] = $destinatario->id;
                    }
                    if($config["email"]["enviar"] && $this->getOrDefault("enviar_email", $destinatario->notificacoes) && !empty($destinatario->email) && !in_array($destinatario->email, $enviados["email"])) {
                        $email = NotificacaoDestinatario::create([
                            "tipo" => "EMAIL",
                            "notificacao_id" => $notificacao->id,
                            "usuario_id" => $destinatario->id
                        ]);
                        $email->save();
                        $enviados["email"][] = $destinatario->email;
                    }
                    if($config["whatsapp"]["enviar"] && $this->getOrDefault("enviar_whatsapp", $destinatario->notificacoes) && !in_array($destinatario->telefone, $enviados["whatsapp"])) {
                        NotificacaoDestinatario::create([
                            "tipo" => "WHATSAPP",
                            "notificacao_id" => $notificacao->id,
                            "usuario_id" => $destinatario->id
                        ])->save();
                        $enviados["whatsapp"][] = $destinatario->telefone;
                    }
                }
            }
        }
    }

    public function applyParams(&$text, $params) {
        foreach($params as $param => $value) $text = str_replace("{{" . $param . "}}", strval($value), $text);
        return $text;
    }

}
