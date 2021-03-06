<?php
	$filename = $argv[1];
	$lines = file($filename);
	$function_name = NULL;
	$allow_override = false;

	$aliases = array();
	$jumptbl = array();
	$map = array();
	$expand = array();
	$expand_max_length = 0;
	$transpose = array();
	$use_map = true;
	$skip = false;

	define('JUMP_MAP', 1);
	define('JUMP_EXPAND', 2);
	define('JUMP_REMOVE', 3);
	define('JUMP_TRANSPOSE_UP', 4);
	define('JUMP_TRANSPOSE_DOWN', 5);

	function register_jump($cp, $jump_type)
	{
		$block = (int) ($cp / 256);
		if (!isset ($GLOBALS['jumptbl'][$block])) {
			$GLOBALS['jumptbl'][$block] = array_fill(0, 256, 0);
		}

		if (isset($GLOBALS['jumptpl'][$block][$cp % 256]) && $GLOBALS['jumptbl'][$block][$cp % 256] && !$GLOBALS['allow_override']) {
			return false;
		}

		$GLOBALS['jumptbl'][$block][$cp % 256] = $jump_type;
		return true;
	}

	function register_map($cp, $res_nr)
	{
		$block = (int) ($cp / 256);
		if (register_jump($cp, JUMP_MAP)) {
			if (!isset ($GLOBALS['map'][$block])) {
				$GLOBALS['map'][$block] = array_fill(0, 256, 0);
			}
			$GLOBALS['map'][$block][$cp % 256] = $res_nr;
		} else {
			echo "Code point $cp is already defined.\n";
		}
	}

	function register_remove($cp)
	{
		$block = (int) ($cp / 256);
		if (!register_jump($cp, JUMP_REMOVE)) {
			echo "Code point $cp is already defined.\n";
		} else {
			$GLOBALS['skip'] = true;
		}
	}

	function register_expand($cp, $res_nrs)
	{
		if (count($res_nrs) == 0) {
			register_remove($cp);
			return;
		}
		if (count($res_nrs) == 1 and $GLOBALS['use_map']) {
			register_map($cp, $res_nrs[0]);
			return;
		}
		$block = (int) ($cp / 256);
		if (register_jump($cp, JUMP_EXPAND)) {
			if (!isset ($GLOBALS['expand'][$block])) {
				$GLOBALS['expand'][$block] = array_fill(0, 256, 0);
			}
			if ((!isset($GLOBALS['expand_max_length'])) or (count($res_nrs) > $GLOBALS['expand_max_length'])) {
				$GLOBALS['expand_max_length'] = count($res_nrs);
			}
			$GLOBALS['expand'][$block][$cp % 256] = array(count($res_nrs), $res_nrs);
		} else {
			echo "Code point $cp is already defined.\n";
		}
	}

	function register_transpose($cp, $res_nrs, $type)
	{
		$block = (int) ($cp / 256);
		if (register_jump($cp, $type)) {
			if (!isset ($GLOBALS['transpose'][$block])) {
				$GLOBALS['transpose'][$block] = array_fill(0, 256, 0);
			}
			$GLOBALS['transpose'][$block][$cp % 256] = $res_nrs[0];
		} else {
			echo "Code point $cp is already defined.\n";
		}
	}
	
	function register_transpose_up($cp, $res_nrs)
	{
		register_transpose($cp, $res_nrs, JUMP_TRANSPOSE_UP);
	}
	
	function register_transpose_down($cp, $res_nrs)
	{
		register_transpose($cp, $res_nrs, JUMP_TRANSPOSE_DOWN);
	}

	function generate_map($name, $table, $type)
	{
		$txt = '';
		$width = ceil(log(max($table), 10));
		$txt .= "static $type {$name}[256] = {\n";
		for ($i = 0; $i < 256; $i++) {
			if ($i % 16 == 0) {
				$txt .= "\t";
			}
			if (!isset($table[$i])) {
				$table[$i] = 0;
			}
			$txt .= sprintf("%{$width}d", $table[$i]);
			if ($i != 255) {
				$txt .= ", ";
			}
			if ($i % 16 == 15) {
				$txt .= "\n";
			}
		}
		$txt .= "};\n\n";
		return $txt;
	}

	function generate_md_map($name, $table, $max_length)
	{
		$txt = '';
		$type = "static us{$max_length}";
		$txt .= "$type {$name}[256] = {\n";
		for ($i = 0; $i < 256; $i++) {
			if ($i % 8 == 0) {
				$txt .= "\t";
			}
			if (!isset($table[$i]) || !is_array($table[$i])) {
				$table[$i] = array(0, array(0));
			}
			$txt .= "{ ". $table[$i][0]. ", ". join(", ", $table[$i][1]). "}";
			if ($i != 255) {
				$txt .= ", ";
			}
			if ($i % 8 == 7) {
				$txt .= "\n";
			}
		}
		$txt .= "};\n\n";
		return $txt;
	}

	function generate_code_header() {
		$txt = <<<ENDHEADER
/*
 * Warning: Do not edit!
 * This file is generated from a transliteration definition table with the name
 * "{$GLOBALS['filename']}".
 */

ENDHEADER;
		$txt .= "#include \"translit_types.h\"\n\n";
		return $txt;
	}

	function generate_code($function_name, $aliases, $jumps, $map, $expand, $expand_max_length, $transpose)
	{
		$txt = '';
		foreach ($jumps as $block => $data) {
			$function = "{$function_name}_jump_map_{$block}";
			$txt .= generate_map($function, $data, 'unsigned char');
		}
		foreach ($map as $block => $data) {
			$function = "{$function_name}_replace_map_{$block}";
			$txt .= generate_map($function, $data, 'unsigned short');
		}
		foreach ($expand as $block => $data) {
			$function = "{$function_name}_expand_map_{$block}";
			$txt .= generate_md_map($function, $data, $expand_max_length);
		}
		foreach ($transpose as $block => $data) {
			$function = "{$function_name}_transpose_map_{$block}";
			$txt .= generate_map($function, $data, 'unsigned short');
		}
		
		$rev_jump = array();
		$table_definition = '';
		/* Generate jump table */
		$c = 0;
		if (count($jumps)) {
			$table_definition .= "static unsigned char *{$function_name}_jump_table[". (count($jumps)). "] = {\n";
			foreach ($jumps as $block => $dummy)
			{
				$table_definition .= "\t{$function_name}_jump_map_{$block},\n";
				$rev_jump[$c] = $block;
				$c++;
			}
			$table_definition .= "};\n";
		}


		$rev_map = array();
		/* Generate map table */
		$c = 0;
		if (count($map)) {
			$table_definition .= "static unsigned short *{$function_name}_map_table[". (count($map)). "] = {\n";
			foreach ($map as $block => $dummy)
			{
				$table_definition .= "\t{$function_name}_replace_map_{$block},\n";
				$rev_map[$block] = $c;
				$c++;
			}
			$table_definition .= "};\n";
		}


		$rev_expand = array();
		/* Generate expand table */
		$c = 0;
		if (count($expand)) {
			$table_definition .= "static us$expand_max_length *{$function_name}_expand_table[". (count($expand)). "] = {\n";
			foreach ($expand as $block => $dummy)
			{
				$table_definition .= "\t{$function_name}_expand_map_{$block},\n";
				$rev_expand[$block] = $c;
				$c++;
			}
			$table_definition .= "};\n";
		}

		$rev_transpose = array();
		/* Generate transpose table */
		$c = 0;
		if (count($transpose)) {
			$table_definition .= "static unsigned short *{$function_name}_transpose_table[". (count($transpose)). "] = {\n";
			foreach ($transpose as $block => $dummy)
			{
				$table_definition .= "\t{$function_name}_transpose_map_{$block},\n";
				$rev_transpose[$block] = $c;
				$c++;
			}
			$table_definition .= "};\n";
		}

		$txt .= <<<ENDCODE
$table_definition

int {$function_name}_convert(unsigned short *in, unsigned int in_length, unsigned short **out, unsigned int *out_length)
{
	unsigned int i, j, no_jump, jump, out_idx;
	unsigned char block, cp;
	unsigned char *jump_map;
	unsigned short *replace_map, *transpose_map;
	us{$expand_max_length} *expand_map;
	unsigned short *tmp_out = NULL;
	unsigned int    str_length;

	/* Init table pointers */
	jump_map = NULL;
	replace_map = NULL;
	transpose_map = NULL;
	expand_map = NULL;
	j = 0;

	/* Determine initial string length */
	str_length = in_length;
	tmp_out = (unsigned short *) malloc((str_length * sizeof(unsigned short)) + 8);
	out_idx = 0;

	/* Loop over input array */
	for (i = 0; i < in_length; i++) {
		if (out_idx >= str_length) {
			str_length += 128;
			tmp_out = (unsigned short *) realloc(tmp_out, str_length * sizeof(unsigned short));
		}
		
		block = in[i] / 256;
		cp    = in[i] % 256;

		no_jump = 0;
		switch (block) {
ENDCODE;
		foreach ($rev_jump as $map_id => $block) {
			$txt .= "\n\t\t\tcase $block: jump_map = {$function_name}_jump_table[$map_id]; ";
			if (isset($map[$block])) {
				$id = $rev_map[$block];
				$txt .= "replace_map = {$function_name}_map_table[$id]; ";
			}
			if (isset($expand[$block])) {
				$id = $rev_expand[$block];
				$txt .= "expand_map = {$function_name}_expand_table[$id]; ";
			}
			if (isset($transpose[$block])) {
				$id = $rev_transpose[$block];
				$txt .= "transpose_map = {$function_name}_transpose_table[$id]; ";
			}
			$txt .= "break;";
		}

		$txt .= <<<ENDCODE

			default: no_jump = 1;
		}
		if (no_jump) {
			jump = 0;
		} else {
			jump = jump_map[cp];
		}

		switch (jump) {
			case 0: /* No changes */
				tmp_out[out_idx] = in[i];
				out_idx++;
				break;

ENDCODE;
		if (count($map)) {
			$txt .= <<<ENDCODE
			case 1: /* Simple mapping */
				tmp_out[out_idx] = replace_map[cp];
				out_idx++;
				break;

ENDCODE;
		}
		if (count($expand)) {
			$txt .= <<<ENDCODE
			case 2: /* Expand to more than one char */
				for (j = 1; j <= expand_map[cp][0]; j++) {
					tmp_out[out_idx] = expand_map[cp][j];
					out_idx++;
				}
				break;

ENDCODE;
		}
		if ($GLOBALS['skip']) {
			$txt .= <<<ENDCODE
			case 3: /* Skip */
				break;

ENDCODE;
		}
		if (count($transpose)) {
			$txt .= <<<ENDCODE
			case 4: /* Transpose Up */
				tmp_out[out_idx] = in[i] + transpose_map[cp];
				out_idx++;
				break;
			case 5: /* Transpose Down */
				tmp_out[out_idx] = in[i] - transpose_map[cp];
				out_idx++;
				break;

ENDCODE;
		}
		$txt .= <<<ENDCODE
		}
	}
	*out_length = out_idx;
	*out = tmp_out;
	return 0;
}

ENDCODE;
		$fp = fopen(basename($GLOBALS['filename'], '.tr').'.c', 'a');
		fwrite($fp, $txt);
		fclose($fp);
	
		$include = fopen("filter_table.h", "a");
		fputs($include, "\t{ \"$function_name\", {$function_name}_convert },\n");
		foreach ($aliases as $alias) {
			fputs($include, "\t{ \"$alias\", {$function_name}_convert },\n");
		}
		fclose($include);
	
		$include = fopen("translit_filters.h", "a");
		fputs($include, "int {$function_name}_convert(unsigned short *in, unsigned int in_length, unsigned short **out, unsigned int *out_length);\n");
		fclose($include);
	}
	
	/* Create file and fileheader */
	$fp = fopen(basename($GLOBALS['filename'], '.tr').'.c', 'w');
	fwrite($fp, generate_code_header());
	fclose($fp);
	
	foreach ($lines as $line) {
		if (preg_match("/^#pragma\s+(.*)$/", $line, $match)) {
			$setting = trim($match[1]);
			if ($setting == 'NOMAP') {
				$use_map = false;
			} else {
				list($setting, $value) = preg_split("/\s+/", $setting);
				switch ($setting) {
					case 'OVERRIDE_ALLOWED':
						$allow_override = ($value == '1');
						break;
					case 'ALIAS':
						$aliases[] = $value;
						break;
					case 'INCLUDE':
						if (isset($filters[$value])) {
							list($o_jumptbl, $o_map, $o_expand, $o_transpose, $o_expand_max_length) = $filters[$value];
							if ($o_expand_max_length > $expand_max_length) {
								$expand_max_length = $o_expand_max_length;
							}
							foreach ($o_jumptbl as $block => $values)
								foreach ($values as $id => $cp)
									if ($cp != 0)
										$jumptbl[$block][$id] = $cp;
							foreach ($o_map as $block => $values)
								foreach ($values as $id => $cp)
									if ($cp != 0)
										$map[$block][$id] = $cp;
							foreach ($o_expand as $block => $values)
								foreach ($values as $id => $cp)
									if ($cp != 0)
										$expand[$block][$id] = $cp;
							foreach ($o_transpose as $block => $values)
								foreach ($values as $id => $cp)
									if ($cp != 0)
										$transpose[$block][$id] = $cp;
								
						} else {
							echo "Can not include filter '$value' as it does not exist (yet)\n";
						}
						break;
				}
			}

		} else
		if (preg_match("/^([a-z_]+):$/", $line, $match)) {
			if ($function_name) {
				echo "Writing code for $function_name\n";
				$code = generate_code($function_name, $aliases, $jumptbl, $map, $expand, $expand_max_length, $transpose);
				$filters[$function_name] = array($jumptbl, $map, $expand, $transpose, $expand_max_length);
				$jumptbl = $map = $expand = $transpose = $aliases = array();
				$expand_max_length = 0;
				$use_map = true;
				$override_allowed = false;
			}
			$function_name = $match[1];
			echo "New function: $function_name\n";
		} else
		if (preg_match('/^(.*?)([+-]?[>=])([^#]*)/', $line, $match)) {
			$def = str_replace(' ', '', $match[1]);
			$res = str_replace(' ', '', $match[3]);

			switch ($match[2]) {
				case '>':
				case '=':
					$func = "register_expand";
					break;
				case '+=':
					$func = "register_transpose_up";
					break;
				case '-=':
					$func = "register_transpose_down";
					break;
			}

			if (preg_match("/^(U\+[0-9A-F]{4})(,U\+[0-9A-F]{4})*$/", $res, $match)) {
				$res_nrs   = array();
				foreach (split(',', preg_replace('/U\+/', '', $match[0])) as $cp) {
					$res_nrs[] = hexdec($cp);
				}
			} else
			if (preg_match('/^U\+([0-9A-F]{4})-U\+([0-9A-F]{4})$/', $res, $match)) {
				$res_begin = hexdec($match[1]);
				$res_end   = hexdec($match[2]);
				$res_nrs   = array();
				for ($i = $res_begin; $i <= $res_end; $i++) {
					$res_nrs[] = $i;
				}
			} else
			if (preg_match('/^U\+([0-9A-F]{4})$/', $res, $match)) {
				$res_nrs = array(hexdec($match[1]));
			} else
			if (preg_match('/^"(.*)"$/', $res, $match)) {
				$res_nrs = array();
				foreach (preg_split('//', stripslashes($match[1])) as $char) {
					if ($char !== '') {
						$res_nrs[] = ord($char);
					}
				}
			}

			if (preg_match("/^(Even|Odd)U\+([0-9A-F]{2})([0-9A-F]{2})-U\+([0-9A-F]{2})([0-9A-F]{2})$/", $def, $m)) {
				$begin = hexdec($m[2].$m[3]);
				$end   = hexdec($m[4].$m[5]);
				for ($i = $begin; $i <= $end; $i++) {
					if ($m[1] == "Even" and ($i % 2) == 0) {
						$func($i, $res_nrs);
					}
					if ($m[1] == "Odd" and ($i % 2) == 1) {
						$func($i, $res_nrs);
					}
				}
			} else if (preg_match("/^U\+([0-9A-F]{2})([0-9A-F]{2})-U\+([0-9A-F]{2})([0-9A-F]{2})$/", $def, $m)) {
				$begin = hexdec($m[1].$m[2]);
				$end   = hexdec($m[3].$m[4]);
				for ($i = $begin; $i <= $end; $i++) {
					$func($i, $res_nrs);
				}
			} else if (preg_match("/^(U\+[0-9A-F]{4})(,U\+[0-9A-F]{4})*$/", $def, $m)) {
				foreach (split(',', preg_replace('/U\+/', '', $m[0])) as $cp) {
					$func(hexdec($cp), $res_nrs);
				}
			}
		}
	}

	$code = generate_code($function_name, $aliases, $jumptbl, $map, $expand, $expand_max_length, $transpose);
?>
