import 'package:flutter/material.dart';
import 'package:connectivity_plus/connectivity_plus.dart';

/// Slim banner shown at the top of the shell when the device is offline.
/// Listens to connectivity changes; hidden while online.
class OfflineBanner extends StatefulWidget {
  final Widget child;
  const OfflineBanner({super.key, required this.child});

  @override
  State<OfflineBanner> createState() => _OfflineBannerState();
}

class _OfflineBannerState extends State<OfflineBanner> {
  bool _offline = false;

  @override
  void initState() {
    super.initState();
    Connectivity().onConnectivityChanged.listen((results) {
      final offline =
          results.isEmpty || results.every((r) => r == ConnectivityResult.none);
      if (mounted && offline != _offline) setState(() => _offline = offline);
    });
    _checkNow();
  }

  Future<void> _checkNow() async {
    try {
      final results = await Connectivity().checkConnectivity();
      final offline =
          results.isEmpty || results.every((r) => r == ConnectivityResult.none);
      if (mounted && offline != _offline) setState(() => _offline = offline);
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        AnimatedSize(
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
          child: _offline
              ? Material(
                  color: const Color(0xFFB45309),
                  child: InkWell(
                    onTap: _checkNow,
                    child: const SizedBox(
                      width: double.infinity,
                      child: Padding(
                        padding: EdgeInsets.symmetric(vertical: 6),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.wifi_off_rounded,
                                size: 16, color: Colors.white),
                            SizedBox(width: 8),
                            Text('You are offline — showing cached content',
                                style: TextStyle(
                                    color: Colors.white, fontSize: 12.5)),
                          ],
                        ),
                      ),
                    ),
                  ),
                )
              : const SizedBox(width: double.infinity),
        ),
        Expanded(child: widget.child),
      ],
    );
  }
}
