/*
 * Warning: Do not edit!
 * This file is generated from a transliteration definition table with the name
 * "lowercase_greek.tr".
 */
#include "translit_types.h"

static unsigned char lowercase_greek_jump_map_3[256] = {
	0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 
	0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 
	0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 
	0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 
	0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 
	0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 
	0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 
	0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 
	0, 0, 0, 0, 0, 0, 4, 0, 4, 4, 4, 0, 4, 0, 4, 4, 
	0, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 
	4, 4, 0, 4, 4, 4, 4, 4, 4, 4, 4, 4, 0, 0, 0, 0, 
	0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 
	0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 
	0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 4, 0, 4, 0, 4, 0, 
	4, 0, 4, 0, 4, 0, 4, 0, 4, 0, 4, 0, 4, 0, 4, 0, 
	0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0
};

static unsigned short lowercase_greek_transpose_map_3[256] = {
	 0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0, 
	 0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0, 
	 0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0, 
	 0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0, 
	 0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0, 
	 0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0, 
	 0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0, 
	 0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0, 
	 0,  0,  0,  0,  0,  0, 38,  0, 37, 37, 37,  0, 64,  0, 63, 63, 
	 0, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 32, 
	32, 32,  0, 32, 32, 32, 32, 32, 32, 32, 32, 32,  0,  0,  0,  0, 
	 0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0, 
	 0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0, 
	 0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  1,  0,  1,  0,  1,  0, 
	 1,  0,  1,  0,  1,  0,  1,  0,  1,  0,  1,  0,  1,  0,  1,  0, 
	 0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0
};

static unsigned char *lowercase_greek_jump_table[1] = {
	lowercase_greek_jump_map_3,
};
static unsigned short *lowercase_greek_transpose_table[1] = {
	lowercase_greek_transpose_map_3,
};


int lowercase_greek_convert(unsigned short *in, unsigned int in_length, unsigned short **out, unsigned int *out_length)
{
	unsigned int i, j, no_jump, jump, out_idx;
	unsigned char block, cp;
	unsigned char *jump_map;
	unsigned short *replace_map, *transpose_map;
	us0 *expand_map;
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
			case 3: jump_map = lowercase_greek_jump_table[0]; transpose_map = lowercase_greek_transpose_table[0]; break;
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
			case 4: /* Transpose Up */
				tmp_out[out_idx] = in[i] + transpose_map[cp];
				out_idx++;
				break;
			case 5: /* Transpose Down */
				tmp_out[out_idx] = in[i] - transpose_map[cp];
				out_idx++;
				break;
		}
	}
	*out_length = out_idx;
	*out = tmp_out;
	return 0;
}
