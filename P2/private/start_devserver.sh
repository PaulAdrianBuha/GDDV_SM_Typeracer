#!/bin/bash

# Navigate to the "public" directory
cd ../public/

# Set the terminal title to "PHP @ <current directory>"
echo -ne "\033]0;PHP @ $(pwd)\007"

# Start a PHP development server on localhost:8000
../PHP/php -S localhost:8000