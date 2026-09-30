import os
import glob

base_dir = r"c:\xampp\htdocs\sikaphub"

files_modified = 0
total_replacements = 0

# Extensions to check
extensions = ['.php', '.js', '.html', '.json']

for root, dirs, files in os.walk(base_dir):
    # Skip .git, vendor, node_modules, scratch
    if '.git' in root or 'vendor' in root or 'scratch' in root or '.venv' in root:
        continue
    
    for file in files:
        if any(file.endswith(ext) for ext in extensions):
            filepath = os.path.join(root, file)
            try:
                with open(filepath, 'r', encoding='utf-8') as f:
                    content = f.read()
                
                # Perform replacements
                new_content = content
                
                # Replace /sikaphub/public/ with /public/
                new_content = new_content.replace('/sikaphub/public/', '/public/')
                
                # Replace /sikaphub/ with / (except inside index.php or .htaccess where we handle fallbacks)
                if 'index.php' not in file and '.htaccess' not in file and 'clean_paths.py' not in file:
                    new_content = new_content.replace('/sikaphub/', '/')
                    new_content = new_content.replace('action="/sikaphub"', 'action="/"')
                    new_content = new_content.replace('href="/sikaphub"', 'href="/"')
                
                if new_content != content:
                    with open(filepath, 'w', encoding='utf-8') as f:
                        f.write(new_content)
                    files_modified += 1
                    print(f"Updated: {filepath}")
            except Exception as e:
                print(f"Error processing {filepath}: {e}")

print(f"\nDone! Modified {files_modified} files.")
