#!/bin/bash

# Specify the changelog file
changelog_file="CHANGELOG.md"
# Specify the composer.json file
composer_file="composer.json"
# Specify the package.json file
package_file="package.json"

# Specify the release version (you can pass this as an argument)
release_version=$1

# Check if the release version is provided
if [[ -z "$release_version" ]]; then
    echo "Usage: $0 <release_version>"
    exit 1
fi

# Get the current date in the format YYYY-MM-DD
current_date=$(date +"%Y-%m-%d")

# Display the first 20 lines of the changelog
head -n 20 "$changelog_file"

# Prompt the user to proceed or exit
read -p "Do you want to proceed with updating the changelog? (y/n): " choice
if [[ "$choice" != "y" ]]; then
    echo "Aborted."
    exit 0
fi

# Use sed to add a new line after '### Unreleased' with the release version and date
sed -i "/## UNRELEASED/a\\## $release_version ($current_date)" "$changelog_file"

# Update the version in composer.json
jq ".version = \"$release_version\"" "$composer_file" > tmpfile && mv tmpfile "$composer_file"

# Update the version in package.json
jq ".version = \"$release_version\"" "$package_file" > tmpfile && mv tmpfile "$package_file"

# Add and commit the changes
git add "$changelog_file" "$composer_file" "$package_file"
git commit -m "Prepare release $release_version"

echo "Files updated and committed for version $release_version."

# Add a Git tag
git tag -a "$release_version" -m "Release $release_version"

# Get the current branch
current_branch=$(git symbolic-ref --short HEAD)

# Push the changelog update, the new tag, and the commit to the current branch
git push origin "$current_branch"
git push origin "$release_version"

echo "Git tag added and pushed for version $release_version to branch $current_branch."
