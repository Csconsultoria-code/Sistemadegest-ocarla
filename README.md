# Sistema da Carla

Sistema simples de gestão: financeiro (receitas e despesas), leads, clientes, serviços e meta 10x.

Página única (`index.html`), sem servidor. Funciona publicada no GitHub Pages.

## Como os dados são guardados

Os dados ficam no navegador de quem usa, neste aparelho. Não há banco de dados online.

- Em **Serviços e metas > Dados e backup**, use **Baixar backup** com frequência.
- Para abrir em outro aparelho ou navegador, use **Importar dados** com o arquivo de backup.
- Limpar os dados do navegador apaga o sistema. Mantenha sempre um backup.

## Dados iniciais

O `index.html` já traz os dados de 17/10/2026 (clientes, receitas, despesas, leads, serviços e meta). Na primeira abertura em cada navegador, eles são carregados sozinhos. Depois disso, cada aparelho guarda as suas próprias alterações.

Atenção: o repositório é público, então esses dados (nomes, telefones, e-mails e valores) ficam visíveis para quem abrir o código.

## Segurança

- A tela de login é só uma trava de tela. Qualquer pessoa que abra o código consegue contorná-la, e os dados ficam no navegador de cada aparelho.
- Arquivos de backup (`dados-*.json`) são bloqueados pelo `.gitignore`.
- Esta versão não envia arquivos. Em cada cliente, guarde o link do documento no Google Drive.
