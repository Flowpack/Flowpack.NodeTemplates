

Run the test with the environment variable `CREATE_SNAPSHOT=1` to created new snapshots (where implemented)

```
# Functional
CREATE_SNAPSHOT=1 ./bin/phpUnit -c Build/BuildEssentials/PhpUnit/FunctionalTests.xml Packages/Application/Flowpack.NodeTemplates/Tests/Functional
./bin/phpUnit -c Build/BuildEssentials/PhpUnit/FunctionalTests.xml Packages/Application/Flowpack.NodeTemplates/Tests/Functional

# Unit
./bin/phpUnit -c Build/BuildEssentials/PhpUnit/UnitTests.xml Packages/Application/Flowpack.NodeTemplates/Tests/Unit
```
