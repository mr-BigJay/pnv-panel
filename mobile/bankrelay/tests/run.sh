#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
: "${BANKRELAY_SDK:?Set BANKRELAY_SDK}"
mkdir -p build/tests
java -m jdk.compiler/com.sun.tools.javac.Main -encoding UTF-8 -cp "$BANKRELAY_SDK/android-35/android.jar:build/apk-classes" -d build/tests tests/*.java
for test in DepositFilterTest ProtoTest SmsRulesTest DiagnosticsTest; do
  java -cp "$BANKRELAY_SDK/android-35/android.jar:build/apk-classes:build/tests" "$test"
done
