#!/bin/bash

echo "Backing up database..."
cp data/database.json data/database.json.bak

echo "Running tests..."
PASSED=true

for test in tests/*.php; do
    echo "Running $test..."
    if php "$test"; then
        echo "$test PASSED"
    else
        echo "$test FAILED"
        PASSED=false
    fi
    echo "-----------------------------------"
done

echo "Restoring database..."
mv data/database.json.bak data/database.json

if [ "$PASSED" = true ]; then
    echo "All tests passed."
    exit 0
else
    echo "Some tests failed."
    exit 1
fi
