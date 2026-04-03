#!/usr/bin/env python3
"""Script to add escapeHtml() wrapping around dynamic data in innerHTML strings."""
import re
import os

FRONTEND = "/workspaces/ateliermoto/frontend"

# Fields that come from API and need escaping (user-controlled strings)
DANGEROUS_FIELDS = [
    # Client fields
    r'c\.prenom', r'c\.nom', r'c\.telephone', r'c\.email', r'c\.adresse', r'c\.notes',
    r'client\.prenom', r'client\.nom', r'client\.telephone', r'client\.email',
    # Vehicle fields
    r'v\.marque', r'v\.modele', r'v\.plaque', r'v\.annee', r'v\.cylindree', r'v\.type_moto',
    r'vehicule\.marque', r'vehicule\.modele', r'vehicule\.plaque',
    # Mechanic fields
    r'meca\.prenom', r'meca\.nom', r'meca\.specialites',
    r'm\.prenom', r'm\.nom', r'm\.specialites', r'm\.couleur',
    # RDV/OR fields
    r'rdv\.type_intervention', r'rdv\.notes', r'rdv\.commentaire',
    r'rc\.type_intervention',
    # Pont fields
    r'pont\.nom', r'pont\.type_pont',
    r'p\.nom', r'p\.type_pont',
    # Facture fields
    r'f\.numero_facture', r'f\.client_prenom', r'f\.client_nom', r'f\.vehicule_desc',
    # OR fields
    r'or\.numero_or', r'or\.travaux',
    # Travaux supp
    r'd\.description', r'd\.or_numero', r'd\.urgence',
    # Rapport
    r'rap\.travaux_realises', r'rap\.alertes', r'rap\.recommandations', r'rap\.pieces_utilisees',
    # Absence
    r'a\.motif', r'a\.notes', r'a\.mecanicien_prenom', r'a\.mecanicien_nom',
    # User
    r'u\.username', r'u\.email', r'u\.role',
    # Generic
    r'it\.nom', r'it\.description',
    r'data\.marque', r'data\.modele', r'data\.annee',
    r'cat\.nom',
    r'h\.heure_ouverture', r'h\.heure_fermeture', r'h\.pause_debut', r'h\.pause_fin',
    # Prestation
    r'presta\.code', r'presta\.nom', r'presta\.description',
    # Pieces
    r'piece\.nom',
    r'ligne\.designation',
    # Modeles moto
    r'modele\.marque', r'modele\.modele', r'modele\.categorie_nom',
]

# Compound expressions that also need escaping
COMPOUND_PATTERNS = [
    # "(x || '').charAt(0)" patterns
    (r"\(([a-zA-Z_.]+) \|\| ''\)\.charAt\(0\)", r"escapeHtml((\1 || '').charAt(0))"),
    # "(x || '')" patterns inside HTML strings
    (r"\(([a-zA-Z_.]+) \|\| ''\)", r"escapeHtml(\1 || '')"),
    # "(x || '-')" patterns
    (r"\(([a-zA-Z_.]+) \|\| '-'\)", r"escapeHtml(\1 || '-')"),
]

def is_already_escaped(content, match_start):
    """Check if a match is already wrapped in escapeHtml()"""
    # Look backwards for escapeHtml(
    before = content[max(0, match_start - 15):match_start]
    return 'escapeHtml(' in before or 'escapeAttr(' in before

def fix_file(filepath):
    with open(filepath, 'r') as f:
        content = f.read()

    original = content
    changes = 0

    # Only process lines that are part of innerHTML assignments or HTML string building
    lines = content.split('\n')
    new_lines = []
    in_html_context = False

    for line in lines:
        # Detect HTML string context (innerHTML, html +=, html =, etc.)
        is_html_line = any(x in line for x in [
            'innerHTML', "html +=", "html =", "html+=", "html=",
            "pontContent =", "pontContent +=",
            "content +=", "content =",
            "modalHtml", "detailHtml", "cardHtml",
            "' +", "' +\n",
        ]) and ("'" in line or '"' in line)

        if not is_html_line:
            new_lines.append(line)
            continue

        new_line = line

        # Apply escapeHtml to dangerous field patterns
        for field_pattern in DANGEROUS_FIELDS:
            # Match field in HTML string context: + field + or + field.something +
            # But NOT when already wrapped in escapeHtml() or in non-HTML contexts

            # Pattern: the field access as a standalone expression in string concat
            # e.g., ' + c.nom + ' or ' + v.marque + '
            regex = r"(?<!escapeHtml\()(?<!escapeAttr\()(?<!\w)(" + field_pattern + r")(?!\w)(?!\s*[\(])"

            for m in re.finditer(regex, new_line):
                full = m.group(0)
                # Check it's not already escaped
                before_ctx = new_line[max(0, m.start()-12):m.start()]
                if 'escapeHtml(' in before_ctx or 'escapeAttr(' in before_ctx:
                    continue
                # Check it's in a string concatenation context
                after_ctx = new_line[m.end():m.end()+5]
                before_word = new_line[max(0, m.start()-3):m.start()]
                if "'" in before_word or '"' in before_word or '+' in before_word or '(' in before_word:
                    new_line = new_line[:m.start()] + "escapeHtml(" + full + ")" + new_line[m.end():]
                    changes += 1
                    break  # Re-process line after change

        new_lines.append(new_line)

    content = '\n'.join(new_lines)

    if content != original:
        # Fix double-wrapping
        content = re.sub(r'escapeHtml\(escapeHtml\(([^)]+)\)\)', r'escapeHtml(\1)', content)
        content = re.sub(r'escapeHtml\(escapeHtml\(([^)]+)\)\)', r'escapeHtml(\1)', content)

        with open(filepath, 'w') as f:
            f.write(content)
        return changes
    return 0

# Process all frontend files
total = 0
for root, dirs, files in os.walk(FRONTEND):
    for f in files:
        if f.endswith(('.js', '.html')) and f != 'core-api.js':
            path = os.path.join(root, f)
            n = fix_file(path)
            if n > 0:
                print(f"  {f}: {n} corrections")
                total += n

print(f"\nTotal: {total} corrections XSS appliquees")
