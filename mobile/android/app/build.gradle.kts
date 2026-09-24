import java.io.FileInputStream
import java.util.Properties

plugins {
    id("com.android.application")
    id("kotlin-android")
    id("com.google.gms.google-services")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

/*
 * Kunci rilis. Keystore & kata sandinya TIDAK PERNAH masuk git (repo ini publik): lokasinya dibaca
 * dari env NETPULSE_KEY_PROPERTIES (disetel bin/build-apk.sh) atau mobile/android/key.properties
 * (di-.gitignore). Isi berkas: storeFile, storePassword, keyAlias, keyPassword.
 *
 * Build rilis GAGAL keras kalau berkas itu tidak ada — jangan pernah kembali diam-diam ke kunci
 * debug: APK bertanda tangan beda tidak bisa meng-update instalasi yang sudah ada.
 */
val keyPropertiesFile: File = System.getenv("NETPULSE_KEY_PROPERTIES")
    ?.takeIf { it.isNotBlank() }
    ?.let { file(it) }
    ?: rootProject.file("key.properties")
val keyProperties = Properties().apply {
    if (keyPropertiesFile.exists()) FileInputStream(keyPropertiesFile).use { load(it) }
}

android {
    namespace = "net.bmkv.netpulse.netpulse_mobile"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
        isCoreLibraryDesugaringEnabled = true
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_17.toString()
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "net.bmkv.netpulse.netpulse_mobile"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        create("release") {
            if (keyPropertiesFile.exists()) {
                storeFile = file(keyProperties.getProperty("storeFile"))
                storePassword = keyProperties.getProperty("storePassword")
                keyAlias = keyProperties.getProperty("keyAlias")
                keyPassword = keyProperties.getProperty("keyPassword")
            }
        }
    }

    buildTypes {
        release {
            signingConfig = signingConfigs.getByName("release")
        }
    }
}

flutter {
    source = "../.."
}

dependencies {
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.0.4")
}

// Gagal keras sebelum task rilis apa pun jalan tanpa kunci rilis.
gradle.taskGraph.whenReady {
    val releaseTask = allTasks.any { it.project == project && it.name.contains("Release") }
    if (releaseTask && !keyPropertiesFile.exists()) {
        throw GradleException(
            "Kunci rilis tidak ditemukan: ${keyPropertiesFile.path}. Setel NETPULSE_KEY_PROPERTIES " +
                "atau buat mobile/android/key.properties. Build rilis TIDAK boleh memakai kunci debug."
        )
    }
}
