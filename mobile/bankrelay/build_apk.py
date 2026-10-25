import os,subprocess,zipfile
from pathlib import Path
root=Path(__file__).resolve().parent
sdk=Path(os.environ['BANKRELAY_SDK']); tools=sdk/'android-15'; android=sdk/'android-35/android.jar'
java=Path(os.environ.get('JAVA_HOME','/usr/lib/jvm/java-17-openjdk-amd64'))/'bin/java'
build=root/'build';classes=build/'apk-classes';classes.mkdir(parents=True,exist_ok=True);dex=build/'dex';dex.mkdir(exist_ok=True)
def run(*args):subprocess.run([str(x) for x in args],check=True,env=dict(os.environ,JAVA_HOME=str(java.parent.parent)))
run(java,'-m','jdk.compiler/com.sun.tools.javac.Main','-encoding','UTF-8','-source','17','-target','17','-classpath',android,'-d',classes,*sorted((root/'app/src/main/java/ir/jay/bankrelay').glob('*.java')))
manifest=(root/'app/src/main/AndroidManifest.xml').read_text().replace('<manifest ', '<manifest package="ir.jay.bankrelay" android:versionCode="4" android:versionName="0.4-experimental" ',1).replace('    <uses-permission','    <uses-sdk android:minSdkVersion="26" android:targetSdkVersion="35" />\n    <uses-permission',1)
(build/'AndroidManifest.xml').write_text(manifest)
run(tools/'aapt2','link','-o',build/'base.apk','--manifest',build/'AndroidManifest.xml','-I',android,'-A',root/'app/src/main/assets')
run(tools/'d8','--min-api','26','--lib',android,'--output',dex,*sorted(classes.rglob('*.class')))
with zipfile.ZipFile(build/'base.apk','a') as z:
 for p in dex.glob('*.dex'):z.write(p,p.name)
run(tools/'zipalign','-f','4',build/'base.apk',build/'aligned.apk')
run(tools/'apksigner','sign','--ks',root/'dev-signing/debug.keystore','--ks-pass','pass:android','--key-pass','pass:android','--out',root/'BankRelay-0.4-experimental.apk',build/'aligned.apk')
run(tools/'apksigner','verify','--verbose','--print-certs',root/'BankRelay-0.4-experimental.apk')
