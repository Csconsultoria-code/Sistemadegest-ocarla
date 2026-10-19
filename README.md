# Sistema da Carla

Sistema simples de gestão: financeiro (receitas e despesas), leads, clientes, serviços e meta 10x.

Página única (`index.html`), sem servidor. Funciona publicada no GitHub Pages.

## Como os dados são guardados

Os dados ficam no navegador de quem usa, neste aparelho. Não há banco de dados online.

- Em **Serviços e metas > Dados e backup**, use **Baixar backup** com frequência.
- Para abrir em outro aparelho ou navegador, use **Importar dados** com o arquivo de backup.
- Limpar os dados do navegador apaga o sistema. Mantenha sempre um backup.

## Segurança

- A tela de login é só uma trava de tela. Qualquer pessoa que abra o código consegue contorná-la, e os dados ficam no navegador de cada aparelho.
- Não coloque arquivos de backup neste repositório. Eles contêm dados de clientes. O `.gitignore` bloqueia arquivos `dados-*.json`.
- Esta versão não envia arquivos. Em cada cliente, guarde o link do documento no Google Drive.
