AEC
===

Robô para monitoramento do site AmorEmCristo

Projeto criado com **ZendFramework**

## Instruções:

- Crie o arquivo application/configs/senhas.ini com as seguintes informações:
<pre>
database.host = "host_do_banco"
database.dbname = "nome_do_banco"
database.username = "usuario_do_banco"
database.password = "senha_do_banco"

site.usuario = "seu_email_do_site"
site.senha = "sua_senha"
</pre>
- Crie o banco de dados que a aplicação criará a tabela no primeiro acesso.

- Inicie o processo para coleta de imagens com o seguinte comando:
<pre>
php scripts/cronjob.php
</pre>

- inicie o robô para monitoramento do site com o seguinte comando:
<pre>
php scripts/carga.php --start -i 30 > scripts/log3 &
</pre>
Onde:
<pre>
-i = intervalo em segundos para a execução do programa
--start = inicia processo
--status = status do processo
--stop = pára o processo

*scripts/log3* é o arquivo de log que contém a data da última requisição
</pre>

**OBS1:** tem muito código em hadcode.

**OBS2:** A busca feita são por perfis de:
<pre>
    sexo: feminino
    local: Rio de Janeiro/capital
    idade: com menos de 35 anos
</pre>
e para alterar, modifique a action *index/search-online* com as informações obtidas na busca desejada

**OBS3:** a action para exibir os usuários encontrados é *index/last* com o seguinte filtro:
<pre>
    sexo: feminino
    estado_civil: solteiro/viuvo
    cidade: rio
    idade: menor ou igual a 35
    tem_filhos: nao
</pre>
- É bom que alguém implemente um formulário de filtro para remover estas informações de hardcode da query.
- Na listagem, o link na imagem joga para o perfil do usuário no site.
- O link no apelido joga para o perfil do usuário coletado pela aplicação

**OBS4:** Na tela do perfil do usário, da aplicação, na parte inferior, tem um formulário para envio de mensagens.
Este formulário apenas funciona para quem é usuário premium no site, sim, eu paguei para ser.

**OBS5:** o script *script/update_all.php* serve para atualizar todos os perfis de usuários.
**CUIDADO** ao rodar este script caso o seu banco já esteja grande, poderá demorar.

**OBS6:** Talvez seja necessário dar permissão em algumas pastas do projeto