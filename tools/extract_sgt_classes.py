"""Extract a fixed, verified SGT dump as data; never execute its SQL."""
import argparse
import hashlib
import json
import pathlib
import re

SOURCE = 'https://www.cnj.jus.br/sgt/enviarArquivo.php?url=85_dump_dados.sql&nome=dump_dados.sql'
SOURCE_SHA256 = '45a1e0750a3cf4ce330e1569d14ae869c356f818a2d2b625e86b9a18975111a5'


def values(text):
    result = []
    pos = 0
    while pos < len(text):
        if text.startswith('NULL', pos):
            result.append(None)
            pos += 4
        elif text[pos] == "'":
            pos += 1
            out = []
            while pos < len(text):
                ch = text[pos]
                pos += 1
                if ch == "'":
                    if pos < len(text) and text[pos] == "'":
                        out.append("'")
                        pos += 1
                        continue
                    break
                if ch == '\\':
                    if pos >= len(text):
                        raise ValueError('Unterminated SQL escape')
                    escaped = text[pos]
                    pos += 1
                    mapping = {'0': '\0', 'n': '\n', 'r': '\r', 't': '\t', 'b': '\b', 'Z': '\x1a', '\\': '\\', "'": "'", '"': '"'}
                    if escaped not in mapping:
                        raise ValueError(f'Unknown escape {escaped!r}')
                    out.append(mapping[escaped])
                else:
                    out.append(ch)
            else:
                raise ValueError('Unterminated SQL string')
            result.append(''.join(out))
        else:
            raise ValueError(f'Unexpected SQL token at {pos}')
        if pos == len(text):
            break
        if text[pos] != ',':
            raise ValueError(f'Unexpected separator at {pos}')
        pos += 1
    return result


def extract(path):
    raw = path.read_bytes()
    if hashlib.sha256(raw).hexdigest() != SOURCE_SHA256:
        raise ValueError('Source dump differs from the reviewed file')
    sql = raw.decode('latin-1')
    pattern = re.compile(r'^INSERT INTO sgt_consulta\.(itens|classes) \(([^\n]+?)\) VALUES \(([\s\S]*?)\);[ \t\r]*$', re.M)
    rows = {'itens': {}, 'classes': {}}
    counts = {'itens': 0, 'classes': 0}
    for match in pattern.finditer(sql):
        table, columns, raw_values = match.groups()
        counts[table] += 1
        keys = columns.split(',')
        parsed = values(raw_values)
        if len(keys) != len(parsed):
            raise ValueError('Field count mismatch')
        row = dict(zip(keys, parsed))
        if table == 'itens' and row['tipo_item'] != 'C':
            continue
        code = int(row['cod_item' if table == 'itens' else 'cod_classe'])
        if code in rows[table]:
            raise ValueError('Duplicate class code')
        rows[table][code] = row
    if counts != {'itens': 8844, 'classes': 842} or len(rows['classes']) != 842 or len(rows['itens']) < 842:
        raise ValueError(f'Unexpected record counts: {len(rows["classes"])}, {len(rows["itens"])}')
    output = []
    for code, cls in sorted(rows['classes'].items()):
        item = rows['itens'].get(code)
        if item is None or cls['just_es_1grau'] not in ('S', 'N'):
            raise ValueError('Missing item or invalid jurisdiction flag')
        output.append({'code': code, 'parent_code': int(item['cod_item_pai']) if item['cod_item_pai'] else None,
                       'name': item['nome'], 'status': item['situacao'], 'valid_from': item['dat_inicio_vigencia'],
                       'valid_until': item['dat_fim_vigencia'], 'hierarchy_type': item['tip_hierarquia_item'],
                       'state_first_degree': cls['just_es_1grau'] == 'S'})
    return {'source': SOURCE, 'source_sha256': hashlib.sha256(raw).hexdigest(), 'records': output}


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('input', type=pathlib.Path)
    parser.add_argument('output', type=pathlib.Path)
    args = parser.parse_args()
    args.output.write_text(json.dumps(extract(args.input), ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
