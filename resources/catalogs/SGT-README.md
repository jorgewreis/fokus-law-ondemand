# Snapshot de classes SGT

`cnj-sgt-classes-dump-85.json` foi gerado a partir do [dump público de dados SGT do CNJ](https://www.cnj.jus.br/sgt/enviarArquivo.php?url=85_dump_dados.sql&nome=dump_dados.sql), obtido em 20/11/2026. SHA-256 do dump: `45a1e0750a3cf4ce330e1569d14ae869c356f818a2d2b625e86b9a18975111a5`. O sufixo 85 identifica o arquivo disponibilizado, não comprova data de vigência nem versão semântica dos dados.

O extrator `tools/extract_sgt_classes.py` interpreta apenas literais de INSERT das tabelas `itens` e `classes`; nunca executa SQL. Exige o hash da fonte e contagens esperadas (8.844 itens, 842 classes). Cruza a identificação da classe com seu item de tipo `C`; preserva nome, código, pai, situação, início/fim de vigência, tipo de hierarquia e habilitação Justiça Estadual 1º grau. O arquivo contém 842 classes; 404 têm a flag de habilitação em 1º grau, inclusive inativas. Os tipos de hierarquia extraídos estão nulos; não presumir terminalidade ou disponibilidade de seleção.

Para reproduzir, forneça o dump exato baixado do CNJ e compare o JSON gerado com o snapshot versionado:

```sh
python tools/extract_sgt_classes.py <dump-baixado> <saida.json>
```

`SgtClassSnapshot` importa a cópia empacotada somente para os alvos permitidos pelo guard sintético, valida seu hash e grava uma fotografia sem sobrescrever registros históricos. Não cria mapeamento entre classes e competências, não ativa escolha de classes e não concede acesso a Processos. A atualidade, semântica de estado e terminalidade devem ser verificadas antes de habilitar seleção ou sincronização automática.
