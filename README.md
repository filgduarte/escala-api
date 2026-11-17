# Músicos Voluntários API

API PHP para cadastro de músicos, instrumentos e disponibilidades, com autenticação JWT e persistência em MySQL/MariaDB.

## Requisitos

- PHP 8.0 ou superior, com a extensão `pdo_mysql`.
- Composer.
- MySQL ou MariaDB em execução.
- Usuário do banco com permissão para criar o banco, tabelas, índices e relacionamentos.

## Configuração

Instale as dependências:

```powershell
composer install
```

Se ainda não existir um `.env`, copie o modelo e preencha suas credenciais:

```powershell
Copy-Item .env.example .env
```

Se o `.env` já existir, mantenha suas configurações. `DB_PORT` é opcional e usa `3306` quando omitido. Não coloque aspas nem comentários ao final dos valores; comentários devem ocupar linhas próprias. `DB_PASS` pode ficar vazio caso o servidor permita essa autenticação.

Use `DB_CHARSET=utf8mb4` para suportar acentos e emojis. O nome do banco aceita de 1 a 64 letras, números, underscores ou hífens.

Configure também `AUTH_USER`, `AUTH_PASS`, `JWT_SECRET`, `JWT_ISSUER` e `JWT_EXPIRATION` para acessar a API. Gere uma chave para `JWT_SECRET` com:

```powershell
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

O `.env` é ignorado pelo Git. Não substitua suas credenciais existentes pelos valores de exemplo.

## Criar o banco e as tabelas

Execute na raiz do projeto:

```powershell
composer db:setup
```

Sem Composer, o mesmo comando pode ser executado diretamente:

```powershell
php scripts/setup-database.php
```

Para utilizar outro arquivo de ambiente, sem mudar o `.env` da aplicação:

```powershell
php scripts/setup-database.php --env=.env.test
```

O script conecta ao servidor usando `DB_HOST`, `DB_PORT`, `DB_USER` e `DB_PASS`, cria `DB_NAME` com `DB_CHARSET` e aplica `database/schema.sql`. Não cria usuários nem insere registros de exemplo.

| Tabela | Finalidade |
| --- | --- |
| `musicians` | Dados dos músicos, com telefone, e-mail e aniversário opcionais. |
| `instruments` | Instrumentos e suas propriedades opcionais. |
| `musician_instruments` | Vínculo único entre músico e instrumento, com prioridade inteira. |
| `availabilities` | Disponibilidade única por músico e data. |

As tabelas usam InnoDB para permitir transações. As chaves estrangeiras impedem referências a músicos ou instrumentos inexistentes. Excluir um músico remove seus vínculos e disponibilidades; excluir um instrumento remove seus vínculos. O índice por data atende à consulta de disponibilidades da API.

O comando usa `IF NOT EXISTS`: pode ser repetido sem apagar registros ou recriar tabelas. Ele é uma instalação inicial, não um sistema de migrations: não modifica a estrutura de tabelas que já existam nem garante que estruturas antigas correspondam ao esquema. MySQL confirma comandos DDL implicitamente; em caso de erro, corrija a causa e execute novamente para criar as tabelas restantes.

Uma execução bem-sucedida retorna código `0`; erros retornam código `1`. Se a conexão falhar, confira se o servidor está ativo e se as credenciais têm as permissões necessárias.

### Falha de conexão: MySQL 2002 ou 2003

O comando cria o banco dentro de um servidor existente. Ele não instala nem inicia o serviço MySQL/MariaDB. Esses códigos indicam que a conexão ao endereço e à porta configurados não foi estabelecida.

No Windows, confira os serviços disponíveis:

```powershell
Get-Service -Name '*mysql*','*maria*'
```

Para iniciar um serviço chamado `MySQL80`, execute em um PowerShell como administrador:

```powershell
Start-Service MySQL80
```

Se utilizar o MySQL do XAMPP, inicie o módulo MySQL pelo painel do XAMPP. Escolha a instalação correspondente às credenciais do seu `.env`.

Depois de iniciar o servidor, confira a porta configurada e execute novamente:

```powershell
Test-NetConnection 127.0.0.1 -Port 3306
composer db:setup
```

Substitua endereço e porta pelos valores de `DB_HOST` e `DB_PORT`, quando diferentes. `TcpTestSucceeded: True` indica que há um servidor aceitando conexões nessa porta; a autenticação será verificada pelo comando de preparação.

## Executar localmente no Windows

Após preparar o banco:

```powershell
php -S 127.0.0.1:8000 -t public public/index.php
```

Faça login em `POST http://127.0.0.1:8000/login` com um objeto JSON contendo `user` e `pass`. Use o token retornado no cabeçalho `Authorization: Bearer SEU_TOKEN` para acessar `/musicos`, `/instrumentos` e `/disponibilidades`.

O servidor embutido é destinado ao desenvolvimento. Em uma hospedagem, configure a raiz pública como `public/` e ajuste a reescrita de URLs ao servidor utilizado.
