# Changelog

Todas as mudanças relevantes do plugin são registradas aqui. A seção de cada versão
(`## [X.Y.Z]`) é publicada nas notas da release e aparece em "Ver detalhes" no painel
do WordPress de quem usa o plugin.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o
versionamento segue [SemVer](https://semver.org/lang/pt-BR/).

## [1.2.0]

### Adicionado
- Horários de funcionamento: a bolinha de cada atendente/departamento no widget passa a
  mostrar se ele está online ou offline agora, com um texto como "Online agora" ou
  "Volta seg às 08:00".
- Horário geral na nova aba **Horários** (Configuração) e, em cada conta, o modo de
  disponibilidade: Sempre online, Horário geral, Horário próprio ou Sempre offline.
- Contas online aparecem primeiro. Quando todas estão offline, o botão flutuante e o
  subtítulo do painel indicam isso. Contas offline continuam clicáveis.
- Novos textos editáveis em Aparência (traduzíveis via WPML) para os status.

### Observação
- Contas existentes ficam em "Sempre online": nada muda no site até que um horário
  seja configurado.

## [1.1.0]

### Adicionado
- Atualizações automáticas pelo painel do WordPress: os sites passam a receber o aviso
  de nova versão e o botão "Atualizar agora", com as versões vindas das releases do GitHub.

### Corrigido
- O pacote de instalação passa a incluir as traduções (`languages/`) e o `uninstall.php`,
  para que a remoção de dados ao desinstalar funcione quando a opção estiver ativada.

## [1.0.0]

- Primeira versão pública.
