import 'package:flutter/material.dart';

/// Bhasebook palette — mirrors the web app's `bhas` Tailwind scale.
class Bhas {
  static const int _v50 = 0xFFeef6ff;
  static const int _v100 = 0xFFd9ebff;
  static const int _v200 = 0xFFbcdcff;
  static const int _v300 = 0xFF8ec6ff;
  static const int _v400 = 0xFF59a6ff;
  static const int _v500 = 0xFF3387fb;
  static const int _v600 = 0xFF1e6be8;
  static const int _v700 = 0xFF1755c4;
  static const int _v800 = 0xFF18489f;
  static const int _v900 = 0xFF19407e;
  static const int _v950 = 0xFF142850;

  static const MaterialColor primary = MaterialColor(_v500, {
    50: Color(_v50),
    100: Color(_v100),
    200: Color(_v200),
    300: Color(_v300),
    400: Color(_v400),
    500: Color(_v500),
    600: Color(_v600),
    700: Color(_v700),
    800: Color(_v800),
    900: Color(_v900),
    950: Color(_v950),
  });

  static const seed = Color(_v500);
}

class AppTheme {
  static ThemeData light() => _base(Brightness.light);
  static ThemeData dark() => _base(Brightness.dark);

  static ThemeData _base(Brightness brightness) {
    final isDark = brightness == Brightness.dark;
    final scheme = ColorScheme.fromSeed(
      seedColor: Bhas.seed,
      brightness: brightness,
    );
    final surface = isDark ? const Color(0xFF101828) : Colors.white;
    final bg = isDark ? const Color(0xFF0B1220) : const Color(0xFFeef6ff);

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme.copyWith(surface: surface),
      scaffoldBackgroundColor: bg,
      appBarTheme: AppBarTheme(
        backgroundColor: surface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        centerTitle: false,
        iconTheme: IconThemeData(color: isDark ? Colors.white : const Color(0xFF0f172a)),
        titleTextStyle: TextStyle(
          color: isDark ? Colors.white : const Color(0xFF0f172a),
          fontSize: 18,
          fontWeight: FontWeight.w700,
        ),
      ),
      cardTheme: CardTheme(
        color: surface,
        elevation: 0,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        margin: EdgeInsets.zero,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: isDark ? const Color(0xFF1a2436) : const Color(0xFFd9ebff),
        hintStyle: TextStyle(color: isDark ? Colors.white38 : Colors.black38),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide.none,
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: Bhas.primary.shade400, width: 2),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: Bhas.primary.shade600,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: Bhas.primary.shade700,
          side: BorderSide(color: Bhas.primary.shade300),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
      chipTheme: ChipThemeData(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        backgroundColor: isDark ? const Color(0xFF1a2436) : const Color(0xFFd9ebff),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: surface,
        indicatorColor: Bhas.primary.shade100,
        labelTextStyle: WidgetStatePropertyAll(
          TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Bhas.primary.shade700),
        ),
      ),
      dividerTheme: DividerThemeData(
        color: isDark ? Colors.white10 : Colors.black.withOpacity(0.06),
        thickness: 1,
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    );
  }
}
