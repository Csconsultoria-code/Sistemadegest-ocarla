# Sistema da Carla

Sistema de gestão: financeiro (receitas e despesas), leads, clientes, serviços e meta 10x.

Versão para hospedagem com PHP (Hostinger): login no servidor e dados em banco SQLite.
**Este repositório não contém dados de clientes nem senhas.**

## Como funciona

- `index.html`: o sistema (tela e regras).
- `api.php` / `lib.php`: login com sessão, proteção CSRF, limite de tentativas e gravação dos dados.
- `instalar.php`: tela de instalação, usada uma única vez para criar o usuário e a senha.
- Os dados ficam em `carla-dados/carla.sqlite`, uma pasta criada **fora** da pasta pública do site (se o servidor não permitir, em `dados/`, bloqueada por `.htaccess`).

## Instalação

1. Publique estes arquivos numa pasta do site (ex.: `public_html/carla`).
2. Abra `https://seusite/carla/instalar.php` e escolha usuário e senha (mínimo 10 caracteres).
3. Entre no sistema e use **Serviços e metas > Importar dados** para carregar um backup (`.json`).

## Backup

Em **Serviços e metas > Dados e backup**, use **Baixar backup**. Guarde o arquivo fora do repositório (`dados-*.json` está no `.gitignore`).
