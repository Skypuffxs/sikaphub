import os

filepath = r"c:\xampp\htdocs\sikaphub\app\views\home\index.php"

with open(filepath, 'r', encoding='utf-8') as f:
    content = f.read()

# Replace /sikaphub/ with /
new_content = content.replace('/sikaphub/', '/')

with open(filepath, 'w', encoding='utf-8') as f:
    f.write(new_content)

print("Updated app/views/home/index.php successfully!")
